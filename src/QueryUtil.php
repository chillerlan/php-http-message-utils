<?php
/**
 * Class QueryUtil
 *
 * @created      27.03.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace chillerlan\HTTP\Utils;

use function array_map, array_merge, call_user_func_array, explode, implode, is_array, is_bool, is_iterable,
	is_numeric, is_string, parse_url, preg_match, preg_replace_callback, rawurldecode, sort, str_replace, trim, uksort,
	urlencode;

use const PHP_QUERY_RFC1738, PHP_QUERY_RFC3986, SORT_STRING;

/**
 *
 */
final class QueryUtil{

	public const BOOLEANS_AS_BOOL       = 0;
	public const BOOLEANS_AS_INT        = 1;
	public const BOOLEANS_AS_STRING     = 2;
	public const BOOLEANS_AS_INT_STRING = 3;

	public const NO_ENCODING = -1;

	/**
	 * Cleans/normalizes an array of query parameters, booleans will be converted according to the given $bool_cast constant.
	 * By default, booleans will be left as-is (BOOLEANS_AS_BOOL) and may result in empty values.
	 * If $remove_empty is set to true, empty and null values will be removed from the array.
	 *
	 * @param iterable  $params
	 * @param int|null  $bool_cast    converts booleans to a type determined like following:
	 *                                BOOLEANS_AS_BOOL      : unchanged boolean value (default)
	 *                                BOOLEANS_AS_INT       : integer values 0 or 1
	 *                                BOOLEANS_AS_STRING    : "true"/"false" strings
	 *                                BOOLEANS_AS_INT_STRING: "0"/"1"
	 *
	 * @param bool|null $remove_empty remove empty and NULL values (default: true)
	 *
	 * @return array
	 */
	public static function cleanParams(iterable $params, int $bool_cast = null, bool $remove_empty = null):array{
		$bool_cast    ??= self::BOOLEANS_AS_BOOL;
		$remove_empty ??= true;

		$cleaned = [];

		foreach($params as $key => $value){

			if(is_iterable($value)){
				// recursion
				$cleaned[$key] = call_user_func_array(__METHOD__, [$value, $bool_cast, $remove_empty]);
			}
			elseif(is_bool($value)){

				if($bool_cast === self::BOOLEANS_AS_BOOL){
					$cleaned[$key] = $value;
				}
				elseif($bool_cast === self::BOOLEANS_AS_INT){
					$cleaned[$key] = (int)$value;
				}
				elseif($bool_cast === self::BOOLEANS_AS_STRING){
					$cleaned[$key] = $value ? 'true' : 'false';
				}
				elseif($bool_cast === self::BOOLEANS_AS_INT_STRING){
					$cleaned[$key] = (string)(int)$value;
				}

			}
			elseif(is_string($value)){
				$value = trim($value);

				if($remove_empty && empty($value)){
					continue;
				}

				$cleaned[$key] = $value;
			}
			else{

				if($remove_empty && (!is_numeric($value) && empty($value))){
					continue;
				}

				$cleaned[$key] = $value;
			}
		}

		return $cleaned;
	}

	/**
	 * Builds a query string from an array of key value pairs.
	 *
	 * Valid values for $encoding are PHP_QUERY_RFC3986 (default) and PHP_QUERY_RFC1738,
	 * any other integer value will be interpreted as "no encoding".
	 *
	 * @link https://github.com/abraham/twitteroauth/blob/57108b31f208d0066ab90a23257cdd7bb974c67d/src/Util.php#L84-L122
	 * @link https://github.com/guzzle/psr7/blob/c0dcda9f54d145bd4d062a6d15f54931a67732f9/src/Query.php#L59-L113
	 */
	public static function build(array $params, int $encoding = null, string $delimiter = null, string $enclosure = null):string{

		if(empty($params)){
			return '';
		}

		$encoding  ??= PHP_QUERY_RFC3986;
		$enclosure ??= '';
		$delimiter ??= '&';

		if($encoding === PHP_QUERY_RFC3986){
			$encode = 'rawurlencode';
		}
		elseif($encoding === PHP_QUERY_RFC1738){
			$encode = 'urlencode';
		}
		else{
			$encode = fn(string $str):string => $str;
		}

		$pair = function(string $key, $value) use ($encode, $enclosure):string{

			if($value === null){
				return $key;
			}

			if(is_bool($value)){
				$value = (int)$value;
			}

			// For each parameter, the name is separated from the corresponding value by an '=' character (ASCII code 61)
			return $key.'='.$enclosure.$encode((string)$value).$enclosure;
		};

		// Parameters are sorted by name, using lexicographical byte value ordering.
		uksort($params, 'strcmp');

		$pairs = [];

		foreach($params as $parameter => $value){
			$parameter = $encode((string)$parameter);

			if(is_array($value)){
				// If two or more parameters share the same name, they are sorted by their value
				sort($value, SORT_STRING);

				foreach($value as $duplicateValue){
					$pairs[] = $pair($parameter, $duplicateValue);
				}

			}
			else{
				$pairs[] = $pair($parameter, $value);
			}

		}

		// Each name-value pair is separated by an '&' character (ASCII code 38)
		return implode($delimiter, $pairs);
	}

	/**
	 * Merges additional query parameters into an existing query string
	 */
	public static function merge(string $uri, array $query):string{
		$parsedquery = self::parse(self::parseUrl($uri)['query'] ?? '');
		$requestURI  = explode('?', $uri)[0];
		$params      = array_merge($parsedquery, $query);

		if(!empty($params)){
			$requestURI .= '?'.self::build($params);
		}

		return $requestURI;
	}

	/**
	 * Parses a query string into an associative array.
	 *
	 * @link https://github.com/guzzle/psr7/blob/c0dcda9f54d145bd4d062a6d15f54931a67732f9/src/Query.php#L9-L57
	 */
	public static function parse(string $querystring, int $urlEncoding = null):array{
		$querystring = trim($querystring, '?'); // handle leftover question marks (e.g. Twitter API "next_results")

		if($querystring === ''){
			return [];
		}

		if($urlEncoding === self::NO_ENCODING){
			$decode = fn(string $str):string => $str;
		}
		elseif($urlEncoding === PHP_QUERY_RFC3986){
			$decode = 'rawurldecode';
		}
		elseif($urlEncoding === PHP_QUERY_RFC1738){
			$decode = 'urldecode';
		}
		else{
			$decode = fn(string $value):string => rawurldecode(str_replace('+', ' ', $value));
		}

		$result = [];

		foreach(explode('&', $querystring) as $pair){
			$parts = explode('=', $pair, 2);
			$key   = $decode($parts[0]);
			$value = isset($parts[1]) ? $decode($parts[1]) : null;

			if(!isset($result[$key])){
				$result[$key] = $value;
			}
			else{

				if(!is_array($result[$key])){
					$result[$key] = [$result[$key]];
				}

				$result[$key][] = $value;
			}
		}

		return $result;
	}

	/**
	 * UTF-8 aware \parse_url() replacement.
	 *
	 * The internal function produces broken output for non ASCII domain names
	 * (IDN) when used with locales other than "C".
	 *
	 * On the other hand, cURL understands IDN correctly only when UTF-8 locale
	 * is configured ("C.UTF-8", "en_US.UTF-8", etc.).
	 *
	 * @see https://bugs.php.net/bug.php?id=52923
	 * @see https://www.php.net/manual/en/function.parse-url.php#114817
	 * @see https://curl.haxx.se/libcurl/c/CURLOPT_URL.html#ENCODING
	 *
	 * @link https://github.com/guzzle/psr7/blob/c0dcda9f54d145bd4d062a6d15f54931a67732f9/src/Uri.php#L89-L130
	 */
	public static function parseUrl(string $url):?array{
		// If IPv6
		$prefix = '';
		/** @noinspection RegExpRedundantEscape */
		if(preg_match('%^(.*://\[[0-9:a-f]+\])(.*?)$%', $url, $matches)){
			/** @var array{0:string, 1:string, 2:string} $matches */
			$prefix = $matches[1];
			$url    = $matches[2];
		}

		$encodedUrl = preg_replace_callback('%[^:/@?&=#]+%usD', fn($matches) => urlencode($matches[0]), $url);
		$result     = parse_url($prefix.$encodedUrl);

		if($result === false){
			return null;
		}

		return array_map('urldecode', $result);
	}

}
