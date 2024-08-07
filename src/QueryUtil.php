<?php
/**
 * Class QueryUtil
 *
 * @created      27.03.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTP\Utils;

use InvalidArgumentException;
use function array_map, array_merge, call_user_func_array, explode, implode, is_array, is_bool,
	is_iterable, is_numeric, is_scalar, is_string, rawurldecode, rawurlencode, sort, strcmp,
	str_replace, trim, uksort, urlencode;
use const PHP_QUERY_RFC1738, PHP_QUERY_RFC3986, SORT_STRING;

final class QueryUtil{

	public const BOOLEANS_AS_BOOL       = 0;
	public const BOOLEANS_AS_INT        = 1;
	public const BOOLEANS_AS_STRING     = 2;
	public const BOOLEANS_AS_INT_STRING = 3;

	public const NO_ENCODING = -1;

	/**
	 * Cleans/normalizes an array of query parameters
	 *
	 * By default, booleans will be left as-is (`BOOLEANS_AS_BOOL`) and may result in empty values.
	 * If `$remove_empty` is set to `true` (default), empty and `null` values will be removed from the array.
	 *
	 * `$bool_cast` converts booleans to a type determined like following:
	 *
	 *   - `BOOLEANS_AS_BOOL`      : unchanged boolean value (default)
	 *   - `BOOLEANS_AS_INT`       : integer values 0 or 1
	 *   - `BOOLEANS_AS_STRING`    : "true"/"false" strings
	 *   - `BOOLEANS_AS_INT_STRING`: "0"/"1"
	 *
	 * @param array<int|string, scalar|bool|null> $params
	 *
	 * @return array<int|string, scalar|bool|null>
	 */
	public static function cleanParams(
		iterable  $params,
		int|null  $bool_cast = null,
		bool|null $remove_empty = null,
	):array{
		$bool_cast    ??= self::BOOLEANS_AS_BOOL;
		$remove_empty ??= true;

		$cleaned = [];

		foreach($params as $key => $value){

			if(is_iterable($value)){
				// recursion
				$cleaned[$key] = call_user_func_array(__METHOD__, [$value, $bool_cast, $remove_empty]);
			}
			elseif(is_bool($value)){

				$cleaned[$key] = match($bool_cast){
					self::BOOLEANS_AS_BOOL       => $value,
					self::BOOLEANS_AS_INT        => (int)$value,
					self::BOOLEANS_AS_STRING     => ($value) ? 'true' : 'false',
					self::BOOLEANS_AS_INT_STRING => (string)(int)$value,
					default                      => throw new InvalidArgumentException('invalid $bool_cast parameter value'),
				};

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
	 * Boolean values will be cast to int(0,1), null values will be removed, leaving only their keys.
	 *
	 * @link https://github.com/abraham/twitteroauth/blob/57108b31f208d0066ab90a23257cdd7bb974c67d/src/Util.php#L84-L122
	 * @link https://github.com/guzzle/psr7/blob/c0dcda9f54d145bd4d062a6d15f54931a67732f9/src/Query.php#L59-L113
	 *
	 * @param array<string, scalar|bool|array<int, scalar|bool|null>|null> $params
	 */
	public static function build(
		array       $params,
		int|null    $encoding = null,
		string|null $delimiter = null,
		string|null $enclosure = null,
	):string{

		if(empty($params)){
			return '';
		}

		$encoding  ??= PHP_QUERY_RFC3986;
		$enclosure ??= '';
		$delimiter ??= '&';

		$encode = match($encoding){
			PHP_QUERY_RFC3986 => rawurlencode(...),
			PHP_QUERY_RFC1738 => urlencode(...),
			default           => fn(string $str):string => $str,
		};

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
		uksort($params, strcmp(...));

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
	 *
	 * @param array<string, scalar|bool|null> $query
	 */
	public static function merge(string $uri, array $query):string{
		$querypart  = (UriUtil::parseUrl($uri)['query'] ?? '');
		$params     = array_merge(self::parse($querypart), $query);
		$requestURI = explode('?', $uri)[0];

		if(!empty($params)){
			$requestURI .= '?'.self::build($params);
		}

		return $requestURI;
	}

	/**
	 * Parses a query string into an associative array.
	 *
	 * @link https://github.com/guzzle/psr7/blob/c0dcda9f54d145bd4d062a6d15f54931a67732f9/src/Query.php#L9-L57
	 *
	 * @return array<string, string|string[]>
	 */
	public static function parse(string $querystring, int|null $urlEncoding = null):array{
		$querystring = trim($querystring, '?'); // handle leftover question marks (e.g. Twitter API "next_results")

		if($querystring === ''){
			return [];
		}

		$decode = match($urlEncoding){
			self::NO_ENCODING => fn(string $str):string => $str,
			PHP_QUERY_RFC3986 => rawurldecode(...),
			PHP_QUERY_RFC1738 => urldecode(...),
			default           => fn(string $value):string => rawurldecode(str_replace('+', ' ', $value)),
		};

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
	 * Recursive rawurlencode
	 *
	 * @param string|array<int, scalar|null> $data
	 * @return string|string[]
	 * @throws \InvalidArgumentException
	 */
	public static function recursiveRawurlencode(mixed $data):array|string{

		if(is_array($data)){
			return array_map(__METHOD__, $data);
		}

		if(!is_scalar($data) && $data !== null){
			throw new InvalidArgumentException('$data is neither scalar nor null');
		}

		return rawurlencode((string)$data);
	}

}
