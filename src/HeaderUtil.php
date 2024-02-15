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

use function array_keys, array_values, count, explode, implode,
	is_array, is_numeric, is_string, strtolower, trim, ucfirst;

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

			// the key is numeric, so $val is either a string or an array
			if(is_numeric($key)){
				// "key: val"
				if(is_string($val)){
					$header = explode(':', $val, 2);

					if(count($header) !== 2){
						continue;
					}

					$key = $header[0];
					$val = $header[1];
				}
				// [$key, $val], ["key" => $key, "val" => $val]
				elseif(is_array($val) && !empty($val)){
					$key = array_keys($val)[0];
					$val = array_values($val)[0];

					// skip if the key is numeric
					if(is_numeric($key)){
						continue;
					}
				}
				else{
					continue;
				}
			}
			// the key is named, so we assume $val holds the header values only, either as string or array
			else{
				if(is_array($val)){
					$val = implode(', ', array_values($val));
				}
			}

			$key = self::normalizeHeaderName($key);
			$val = trim((string)($val ?? ''));

			// skip if the header already exists but the current value is empty
			if(isset($normalized[$key]) && empty($val)){
				continue;
			}

			// cookie headers may appear multiple times
			// https://tools.ietf.org/html/rfc6265#section-4.1.2
			if($key === 'Set-Cookie'){
				// we'll just collect the last value here and leave parsing up to you :P
				$normalized[$key][strtolower(explode('=', $val, 2)[0])] = $val;
			}
			// combine header fields with the same name
			// https://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
			else{
				isset($normalized[$key]) && !empty($normalized[$key])
					? $normalized[$key] .= ', '.$val
					: $normalized[$key] = $val;
			}
		}

		return $normalized;
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
	 */
	public static function trimValues(iterable $values):iterable{

		foreach($values as &$value){
			$value = trim((string)($value ?? ''), " \t");
		}

		return $values;
	}

	/**
	 * Normalizes a header name, e.g. "con TENT- lenGTh" -> "Content-Length"
	 */
	public static function normalizeHeaderName(string $name):string{
		$parts = explode('-', $name);

		foreach($parts as &$part){
			// we'll remove any spaces in the name part, e.g. "con tent" -> "content"
			$part = ucfirst(strtolower(str_replace(' ', '', trim($part))));
		}

		return implode('-', $parts);
	}

}
