<?php
/**
 * Class UriUtils
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTP\Utils;

use Psr\Http\Message\UriInterface;
use function array_filter, array_map, explode, implode, parse_url, preg_match,
	preg_replace_callback, rawurldecode, urldecode, urlencode;

final class UriUtil{

	public const URI_DEFAULT_PORTS = [
		'http'   => 80,
		'https'  => 443,
		'ftp'    => 21,
		'gopher' => 70,
		'nntp'   => 119,
		'news'   => 119,
		'telnet' => 23,
		'tn3270' => 23,
		'imap'   => 143,
		'pop'    => 110,
		'ldap'   => 389,
	];

	/**
	 * Checks whether the UriInterface has a port set and if that port is one of the default ports for the given scheme
	 */
	public static function isDefaultPort(UriInterface $uri):bool{
		$port   = $uri->getPort();
		$scheme = $uri->getScheme();

		return $port === null || (isset(self::URI_DEFAULT_PORTS[$scheme]) && $port === self::URI_DEFAULT_PORTS[$scheme]);
	}

	/**
	 * Checks Whether the URI is absolute, i.e. it has a scheme.
	 *
	 * An instance of UriInterface can either be an absolute URI or a relative reference. This method returns true
	 * if it is the former. An absolute URI has a scheme. A relative reference is used to express a URI relative
	 * to another URI, the base URI. Relative references can be divided into several forms:
	 * - network-path references, e.g. '//example.com/path'
	 * - absolute-path references, e.g. '/path'
	 * - relative-path references, e.g. 'subpath'
	 *
	 * @see self::isNetworkPathReference
	 * @see self::isAbsolutePathReference
	 * @see self::isRelativePathReference
	 * @see https://tools.ietf.org/html/rfc3986#section-4
	 */
	public static function isAbsolute(UriInterface $uri):bool{
		return $uri->getScheme() !== '';
	}

	/**
	 * Checks Whether the URI is a network-path reference.
	 *
	 * A relative reference that begins with two slash characters is termed a network-path reference.
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-4.2
	 */
	public static function isNetworkPathReference(UriInterface $uri):bool{
		return $uri->getScheme() === '' && $uri->getAuthority() !== '';
	}

	/**
	 * Checks Whether the URI is an absolute-path reference.
	 *
	 * A relative reference that begins with a single slash character is termed an absolute-path reference.
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-4.2
	 */
	public static function isAbsolutePathReference(UriInterface $uri):bool{
		return $uri->getScheme() === '' && $uri->getAuthority() === '' && isset($uri->getPath()[0]) && $uri->getPath()[0] === '/';
	}

	/**
	 * Checks Whether the URI is a relative-path reference.
	 *
	 * A relative reference that does not begin with a slash character is termed a relative-path reference.
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-4.2
	 */
	public static function isRelativePathReference(UriInterface $uri):bool{
		return $uri->getScheme() === '' && $uri->getAuthority() === '' && (!isset($uri->getPath()[0]) || $uri->getPath()[0] !== '/');
	}

	/**
	 * Removes a specific query string value.
	 *
	 * Any existing query string values that exactly match the provided $key are removed.
	 */
	public static function withoutQueryValue(UriInterface $uri, string $key):UriInterface{
		$current = $uri->getQuery();

		if($current === ''){
			return $uri;
		}

		$result = array_filter(explode('&', $current), fn($part) => rawurldecode(explode('=', $part)[0]) !== rawurldecode($key));

		return $uri->withQuery(implode('&', $result));
	}

	/**
	 * Adds a specific query string value.
	 *
	 * Any existing query string values that exactly match the provided $key are
	 * removed and replaced with the given $key $value pair.
	 *
	 * A value of null will set the query string key without a value, e.g. "key" instead of "key=value".
	 */
	public static function withQueryValue(UriInterface $uri, string $key, string|null $value = null):UriInterface{
		$current = $uri->getQuery();

		$result = ($current !== '')
			? array_filter(explode('&', $current), fn($part) => rawurldecode(explode('=', $part)[0]) !== rawurldecode($key))
			: [];

		// Query string separators ("=", "&") within the key or value need to be encoded
		// (while preventing double-encoding) before setting the query string. All other
		// chars that need percent-encoding will be encoded by withQuery().
		$replaceQuery = ['=' => '%3D', '&' => '%26'];
		$key          = strtr($key, $replaceQuery);

		$result[] = ($value !== null)
			? $key.'='.strtr($value, $replaceQuery)
			: $key;

		return $uri->withQuery(implode('&', $result));
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
	 *
	 * @return array{scheme?: string, host?: int|string, port?: string, user?: string, pass?: string, path?: string, query?: string, fragment?: string}|null
	 */
	public static function parseUrl(string $url):array|null{
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

		$parsed = array_map(urldecode(...), $result);

		if(isset($parsed['port'])){
			$parsed['port'] = (int)$parsed['port'];
		}

		return $parsed;
	}

}
