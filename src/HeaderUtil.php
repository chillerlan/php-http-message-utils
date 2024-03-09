<?php
/**
 * Class HeaderUtil
 *
 * @created      28.03.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

declare(strict_types=1);

namespace chillerlan\HTTP\Utils;

use InvalidArgumentException;
use function array_keys, array_values, count, explode, implode, is_array,
	is_numeric, is_scalar, is_string, str_replace, strtolower, trim, ucfirst;

/**
 *
 */
final class HeaderUtil{

	/**
	 * Normalizes an array of header lines to format ["Name" => "Value (, Value2, Value3, ...)", ...]
	 * An exception is being made for Set-Cookie, which holds an array of values for each cookie.
	 * For multiple cookies with the same name, only the last value will be kept.
	 */
	public static function normalize(iterable $headers):array{
		$normalized = [];

		foreach($headers as $key => $val){

			// the key is numeric, so $val is either a string or an array that contains both
			if(is_numeric($key)){
				[$key, $val] = self::normalizeKV($val);

				if($key === null){
					continue;
				}
			}

			$key = self::normalizeHeaderName($key);

			// cookie headers may appear multiple times -  we'll just collect the last value here
			// https://datatracker.ietf.org/doc/html/rfc6265#section-5.2
			if($key === 'Set-Cookie'){
				$name = fn(string $v):string => trim(strtolower(explode('=', $v, 2)[0]));

				// array received from Message::getHeaders()
				if(is_array($val)){
					foreach(self::trimValues($val) as $line){
						$normalized[$key][$name($line)] = trim($line);
					}
				}
				else{
					$val = self::trimValues([$val])[0];

					$normalized[$key][$name($val)] = $val;
				}
			}
			// combine header fields with the same name
			// https://datatracker.ietf.org/doc/html/rfc7230#section-3.2
			else{

				if(!is_array($val)){
					$val = [$val];
				}

				/** @noinspection PhpParamsInspection */
				$val = implode(', ', array_values(self::trimValues($val)));

				// skip if the header already exists but the current value is empty
				if(isset($normalized[$key]) && $val === ''){
					continue;
				}

				!empty($normalized[$key])
					? $normalized[$key] .= ', '.$val
					: $normalized[$key] = $val;
			}
		}

		return $normalized;
	}

	/**
	 * Extracts a key:value pair from the given value and returns it as 2-element array.
	 * If the key cannot be parsed, both array values will be `null`.
	 */
	protected static function normalizeKV(mixed $value):array{

		// "key: val"
		if(is_string($value)){
			$kv = explode(':', $value, 2);

			if(count($kv) === 2){
				return $kv;
			}
		}
		// [$key, $val], ["key" => $key, "val" => $val]
		elseif(is_array($value) && !empty($value)){
			$key = array_keys($value)[0];
			$val = array_values($value)[0];

			if(is_string($key)){
				return [$key, $val];
			}
		}

		return [null, null];
	}

	/**
	 * Trims whitespace from the header values.
	 *
	 * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
	 *
	 * header-field = field-name ":" OWS field-value OWS
	 * OWS          = *( SP / HTAB )
	 *
	 * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
	 * @see https://github.com/advisories/GHSA-wxmh-65f7-jcvw
	 */
	public static function trimValues(iterable $values):iterable{

		foreach($values as &$value){

			if(!is_scalar($value) && $value !== null){
				throw new InvalidArgumentException('value is expected to be scalar or null');
			}

			$value = trim(str_replace(["\r", "\n"], '', (string)($value ?? '')));
		}

		return $values;
	}

	/**
	 * Normalizes a header name, e.g. "con TENT- lenGTh" -> "Content-Length"
	 *
	 * @see https://github.com/advisories/GHSA-wxmh-65f7-jcvw
	 */
	public static function normalizeHeaderName(string $name):string{
		// we'll remove any spaces as well as CRLF in the name, e.g. "con tent" -> "content"
		$parts = explode('-', str_replace([' ', "\r", "\n"], '', $name));

		foreach($parts as &$part){
			$part = ucfirst(strtolower(trim($part)));
		}

		return implode('-', $parts);
	}

}
