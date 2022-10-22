<?php
/**
 * @created      28.08.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 */

namespace chillerlan\HTTP\Utils;

use TypeError;
use Psr\Http\Message\UriInterface;

use function array_filter, array_map, explode, implode, is_array, is_scalar, pathinfo, rawurldecode, rawurlencode, strtolower;

use const PATHINFO_EXTENSION;

const CHILLERLAN_PSR7_UTIL_INCLUDES = true;

/**
 * @link http://svn.apache.org/repos/asf/httpd/httpd/branches/1.3.x/conf/mime.types
 */
const MIMETYPES = [
	'3gp'     => 'video/3gpp',
	'7z'      => 'application/x-7z-compressed',
	'aac'     => 'audio/x-aac',
	'ai'      => 'application/postscript',
	'aif'     => 'audio/x-aiff',
	'asc'     => 'text/plain',
	'asf'     => 'video/x-ms-asf',
	'atom'    => 'application/atom+xml',
	'avi'     => 'video/x-msvideo',
	'bmp'     => 'image/bmp',
	'bz2'     => 'application/x-bzip2',
	'cer'     => 'application/pkix-cert',
	'crl'     => 'application/pkix-crl',
	'crt'     => 'application/x-x509-ca-cert',
	'css'     => 'text/css',
	'csv'     => 'text/csv',
	'cu'      => 'application/cu-seeme',
	'deb'     => 'application/x-debian-package',
	'doc'     => 'application/msword',
	'docx'    => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'dvi'     => 'application/x-dvi',
	'eot'     => 'application/vnd.ms-fontobject',
	'eps'     => 'application/postscript',
	'epub'    => 'application/epub+zip',
	'etx'     => 'text/x-setext',
	'flac'    => 'audio/flac',
	'flv'     => 'video/x-flv',
	'gif'     => 'image/gif',
	'gz'      => 'application/gzip',
	'htm'     => 'text/html',
	'html'    => 'text/html',
	'ico'     => 'image/x-icon',
	'ics'     => 'text/calendar',
	'ini'     => 'text/plain',
	'iso'     => 'application/x-iso9660-image',
	'jar'     => 'application/java-archive',
	'jpe'     => 'image/jpeg',
	'jpeg'    => 'image/jpeg',
	'jpg'     => 'image/jpeg',
	'js'      => 'text/javascript',
	'json'    => 'application/json',
	'latex'   => 'application/x-latex',
	'log'     => 'text/plain',
	'm4a'     => 'audio/mp4',
	'm4v'     => 'video/mp4',
	'mid'     => 'audio/midi',
	'midi'    => 'audio/midi',
	'mov'     => 'video/quicktime',
	'mkv'     => 'video/x-matroska',
	'mp3'     => 'audio/mpeg',
	'mp4'     => 'video/mp4',
	'mp4a'    => 'audio/mp4',
	'mp4v'    => 'video/mp4',
	'mpe'     => 'video/mpeg',
	'mpeg'    => 'video/mpeg',
	'mpg'     => 'video/mpeg',
	'mpg4'    => 'video/mp4',
	'oga'     => 'audio/ogg',
	'ogg'     => 'audio/ogg',
	'ogv'     => 'video/ogg',
	'ogx'     => 'application/ogg',
	'pbm'     => 'image/x-portable-bitmap',
	'pdf'     => 'application/pdf',
	'pgm'     => 'image/x-portable-graymap',
	'png'     => 'image/png',
	'pnm'     => 'image/x-portable-anymap',
	'ppm'     => 'image/x-portable-pixmap',
	'ppt'     => 'application/vnd.ms-powerpoint',
	'pptx'    => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	'ps'      => 'application/postscript',
	'qt'      => 'video/quicktime',
	'rar'     => 'application/x-rar-compressed',
	'ras'     => 'image/x-cmu-raster',
	'rss'     => 'application/rss+xml',
	'rtf'     => 'application/rtf',
	'sgm'     => 'text/sgml',
	'sgml'    => 'text/sgml',
	'svg'     => 'image/svg+xml',
	'swf'     => 'application/x-shockwave-flash',
	'tar'     => 'application/x-tar',
	'tif'     => 'image/tiff',
	'tiff'    => 'image/tiff',
	'torrent' => 'application/x-bittorrent',
	'ttf'     => 'application/x-font-ttf',
	'txt'     => 'text/plain',
	'wav'     => 'audio/x-wav',
	'webm'    => 'video/webm',
	'wma'     => 'audio/x-ms-wma',
	'wmv'     => 'video/x-ms-wmv',
	'woff'    => 'application/x-font-woff',
	'wsdl'    => 'application/wsdl+xml',
	'xbm'     => 'image/x-xbitmap',
	'xls'     => 'application/vnd.ms-excel',
	'xlsx'    => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'xml'     => 'application/xml',
	'xpm'     => 'image/x-xpixmap',
	'xwd'     => 'image/x-xwindowdump',
	'yaml'    => 'text/yaml',
	'yml'     => 'text/yaml',
	'zip'     => 'application/zip',
];

/**
 * Get the mime type for the given file extension
 */
function getMimetypeFromExtension(string $extension):?string{
	return MIMETYPES[strtolower($extension)] ?? null;
}

/**
 * Get the mime type from a file name
 */
function getMimetypeFromFilename(string $filename):?string{
	return getMimetypeFromExtension(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Recursive rawurlencode
 *
 * @param string|string[] $data
 *
 * @return string|string[]
 * @throws \TypeError
 */
function r_rawurlencode($data){

	if(is_array($data)){
		return array_map(__FUNCTION__, $data);
	}

	if(!is_scalar($data) && $data !== null){
		throw new TypeError('$data is neither scalar nor null');
	}

	return rawurlencode((string)$data);
}

const URI_DEFAULT_PORTS = [
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
function uriIsDefaultPort(UriInterface $uri):bool{
	$port   = $uri->getPort();
	$scheme = $uri->getScheme();

	return $port === null || (isset(URI_DEFAULT_PORTS[$scheme]) && $port === URI_DEFAULT_PORTS[$scheme]);
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
 * @see  Uri::isNetworkPathReference
 * @see  Uri::isAbsolutePathReference
 * @see  Uri::isRelativePathReference
 * @link https://tools.ietf.org/html/rfc3986#section-4
 */
function uriIsAbsolute(UriInterface $uri):bool{
	return $uri->getScheme() !== '';
}

/**
 * Checks Whether the URI is a network-path reference.
 *
 * A relative reference that begins with two slash characters is termed an network-path reference.
 *
 * @link https://tools.ietf.org/html/rfc3986#section-4.2
 */
function uriIsNetworkPathReference(UriInterface $uri):bool{
	return $uri->getScheme() === '' && $uri->getAuthority() !== '';
}

/**
 * Checks Whether the URI is a absolute-path reference.
 *
 * A relative reference that begins with a single slash character is termed an absolute-path reference.
 *
 * @link https://tools.ietf.org/html/rfc3986#section-4.2
 */
function uriIsAbsolutePathReference(UriInterface $uri):bool{
	return $uri->getScheme() === '' && $uri->getAuthority() === '' && isset($uri->getPath()[0]) && $uri->getPath()[0] === '/';
}

/**
 * Checks Whether the URI is a relative-path reference.
 *
 * A relative reference that does not begin with a slash character is termed a relative-path reference.
 *
 * @link https://tools.ietf.org/html/rfc3986#section-4.2
 */
function uriIsRelativePathReference(UriInterface $uri):bool{
	return $uri->getScheme() === '' && $uri->getAuthority() === '' && (!isset($uri->getPath()[0]) || $uri->getPath()[0] !== '/');
}

/**
 * Removes a specific query string value.
 *
 * Any existing query string values that exactly match the provided $key are removed.
 */
function uriWithoutQueryValue(UriInterface $uri, string $key):UriInterface{
	$current = $uri->getQuery();

	if($current === ''){
		return $uri;
	}

	$decodedKey = rawurldecode($key);

	$result = array_filter(explode('&', $current), function($part) use ($decodedKey){
		return rawurldecode(explode('=', $part)[0]) !== $decodedKey;
	});

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
function uriWithQueryValue(UriInterface $uri, string $key, string $value = null):UriInterface{
	$current = $uri->getQuery();

	if($current === ''){
		$result = [];
	}
	else{
		$decodedKey = rawurldecode($key);
		$result     = array_filter(explode('&', $current), function($part) use ($decodedKey){
			return rawurldecode(explode('=', $part)[0]) !== $decodedKey;
		});
	}

	// Query string separators ("=", "&") within the key or value need to be encoded
	// (while preventing double-encoding) before setting the query string. All other
	// chars that need percent-encoding will be encoded by withQuery().
	$replaceQuery = ['=' => '%3D', '&' => '%26'];
	$key          = strtr($key, $replaceQuery);

	$result[] = $value !== null
		? $key.'='.strtr($value, $replaceQuery)
		: $key;

	return $uri->withQuery(implode('&', $result));
}
