# chillerlan/php-http-message-utils

A collection of utilities for use with [PSR-7 Message implementations](https://www.php-fig.org/psr/psr-7/).

[![PHP Version Support][php-badge]][php]
[![version][packagist-badge]][packagist]
[![license][license-badge]][license]
[![Continuous Integration][gh-action-badge]][gh-action]
[![Coverage][coverage-badge]][coverage]
[![Scrunitizer][scrutinizer-badge]][scrutinizer]
[![Packagist downloads][downloads-badge]][downloads]

[php-badge]: https://img.shields.io/packagist/php-v/chillerlan/php-http-message-utils?logo=php&color=8892BF
[php]: https://www.php.net/supported-versions.php
[packagist-badge]: https://img.shields.io/packagist/v/chillerlan/php-http-message-utils?logo=packagist
[packagist]: https://packagist.org/packages/chillerlan/php-http-message-utils
[license-badge]: https://img.shields.io/github/license/chillerlan/php-http-message-utils
[license]: https://github.com/chillerlan/php-http-message-utils/blob/main/LICENSE
[coverage-badge]: https://img.shields.io/codecov/c/github/chillerlan/php-http-message-utils?logo=codecov
[coverage]: https://codecov.io/github/chillerlan/php-http-message-utils
[scrutinizer-badge]: https://img.shields.io/scrutinizer/g/chillerlan/php-http-message-utils?logo=scrutinizer
[scrutinizer]: https://scrutinizer-ci.com/g/chillerlan/php-http-message-utils
[downloads-badge]: https://img.shields.io/packagist/dt/chillerlan/php-http-message-utils?logo=packagist
[downloads]: https://packagist.org/packages/chillerlan/php-http-message-utils/stats
[gh-action-badge]: https://img.shields.io/github/actions/workflow/status/chillerlan/php-http-message-utils/ci.yml?branch=main&logo=github
[gh-action]: https://github.com/chillerlan/php-http-message-utils/actions/workflows/ci.yml?query=branch%3Amain

# Documentation

## Requirements
- PHP 8.1+
  - `ext-json`, `ext-simplexml`, `ext-zlib`
  - for `MessageUtil::decompress()`: `ext-br` [kjdev/php-ext-brotli](https://github.com/kjdev/php-ext-brotli) and `ext-zstd` [kjdev/php-ext-zstd](https://github.com/kjdev/php-ext-zstd)

## Installation
**requires [composer](https://getcomposer.org)**

`composer.json` (note: replace `dev-main` with a [version boundary](https://getcomposer.org/doc/articles/versions.md))
```json
{
	"require": {
		"php": "^8.1",
		"chillerlan/php-http-message-utils": "dev-main"
	}
}
```
Profit!

## API
The following classes contain static methods for use with PSR-7 http message objects.

### `HeaderUtil`
| method                      | return  | info                                                                                                                                                                                                                                                                     |
|-----------------------------|---------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `normalize(array $headers)` | `array` | Normalizes an array of header lines to format `["Name" => "Value (, Value2, Value3, ...)", ...]` An exception is being made for `Set-Cookie`, which holds an array of values for each cookie. For multiple cookies with the same name, only the last value will be kept. |

### `QueryUtil`
| method                                                                                           | return   | info                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
|--------------------------------------------------------------------------------------------------|----------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `cleanParams(iterable $params, int $bool_cast = null, bool $remove_empty = null)`                | `array`  | Cleans/normalizes an array of query parameters, booleans will be converted according to the given `$bool_cast` constant. By default, booleans will be left as-is (`Query::BOOLEANS_AS_BOOL`) and may result in empty values. If `$remove_empty` is set to true, empty non-boolean and null values will be removed from the array. The `Query` class provides the following constants for `$bool_cast`:<br>`BOOLEANS_AS_BOOL`: unchanged boolean value (default)<br>`BOOLEANS_AS_INT`: integer values 0 or 1<br>`BOOLEANS_AS_STRING`: "true"/"false" strings<br>`BOOLEANS_AS_INT_STRING`: "0"/"1" strings |
| `build(array $params, int $encoding = null, string $delimiter = null, string $enclosure = null)` | `string` | Builds a query string from an array of key value pairs, similar to [`http_build_query`](https://www.php.net/manual/en/function.http-build-query). Valid values for `$encoding` are `PHP_QUERY_RFC3986` (default) and `PHP_QUERY_RFC1738`, any other integer value will be interpreted as "no encoding" (`Query::NO_ENCODING`).                                                                                                                                                                                                                                                                           |
| `merge(string $uri, array $query)`                                                               | `string` | Merges additional query parameters into an existing query string.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `parse(string $querystring, int $urlEncoding = null)`                                            | `array`  | Parses a query string into an associative array, similar to [`parse_str`](https://www.php.net/manual/en/function.parse-str) (without the inconvenient usage of a by-reference result variable).                                                                                                                                                                                                                                                                                                                                                                                                          |
| `parseUrl(string $url)`                                                                          | `?array` | UTF-8 aware `\parse_url()` replacement.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |

### `MessageUtil`
| method                                                      | return   | info                                                                                                                                                                                                                                                  |
|-------------------------------------------------------------|----------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `getContents(MessageInterface $message)`                    | `string` | Reads the body content of a `MessageInterface` and makes sure to rewind.                                                                                                                                                                              |
| `decodeJSON(MessageInterface $message, bool $assoc = null)` | mixed    | fetches the body of a `MessageInterface` and converts it to a JSON object (`stdClass`) or an associative array if `$assoc` is set to `true` and returns the result.                                                                                   |
| `decodeXML(MessageInterface $message, bool $assoc = null)`  | mixed    | fetches the body of a `MessageInterface` and converts it to a `SimpleXMLElement` or an associative array if `$assoc` is set to `true` and returns the result.                                                                                         |
| `toString(MessageInterface $message)`                       | `string` | Returns the string representation of an HTTP message.                                                                                                                                                                                                 |
| `decompress(MessageInterface $message)`                     | `string` | Decompresses the message content according to the `Content-Encoding` header (`compress`, `deflate`, `gzip`, `br`, `zstd`) and returns the decompressed data. `br` and `zstd` will throw a `RuntimeException` if the respecive extensions are missing. |

### `UriUtil`
| method                                                                 | return         | info                                                                                                                                                                                                                                                                        |
|------------------------------------------------------------------------|----------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `isDefaultPort(UriInterface $uri)`                                     | `bool`         | Checks whether the `UriInterface` has a port set and if that port is one of the default ports for the given scheme.                                                                                                                                                         |
| `isAbsolute(UriInterface $uri)`                                        | `bool`         | Checks whether the URI is absolute, i.e. it has a scheme.                                                                                                                                                                                                                   |
| `isNetworkPathReference(UriInterface $uri)`                            | `bool`         | Checks whether the URI is a network-path reference.                                                                                                                                                                                                                         |
| `isAbsolutePathReference(UriInterface $uri)`                           | `bool`         | Checks whether the URI is a absolute-path reference.                                                                                                                                                                                                                        |
| `isRelativePathReference(UriInterface $uri)`                           | `bool`         | Checks whether the URI is a relative-path reference.                                                                                                                                                                                                                        |
| `withoutQueryValue(UriInterface $uri, string $key)`                    | `UriInterface` | Removes a specific query string value. Any existing query string values that exactly match the provided `$key` are removed.                                                                                                                                                 |
| `withQueryValue(UriInterface $uri, string $key, string $value = null)` | `UriInterface` | Adds a specific query string value. Any existing query string values that exactly match the provided `$key` are removed and replaced with the given `$key`-`$value` pair. A value of null will set the query string key without a value, e.g. "key" instead of "key=value". |

### `ServerUtil`
The `ServerUtil` object requires a set of [PSR-17 factories](https://www.php-fig.org/psr/psr-17/) on invocation, namely `ServerRequestFactoryInterface`, `UriFactoryInterface`, `UploadedFileFactoryInterface` and `StreamFactoryInterface`.
It provides convenience methods to create server requests, URIs and uploaded files from the [superglobals](https://www.php.net/manual/en/language.variables.superglobals.php).

| method                                        | return                                               | info                                                                                                                                                                                                        |
|-----------------------------------------------|------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `createServerRequestFromGlobals()`            | `ServerRequestInterface`                             | Returns a ServerRequest object populated from the superglobals `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES` and `$_SERVER`.                                                                                     |
| `createUriFromGlobals()`                      | `UriInterface`                                       | Creates an Uri populated with values from [`$_SERVER`](https://www.php.net/manual/en/reserved.variables.server) (`HTTP_HOST`, `SERVER_NAME`, `SERVER_ADDR`, `SERVER_PORT`, `REQUEST_URI`, `QUERY_STRING`).  |
| `normalizeFiles(array $files)`                | `UploadedFileInterface[]`                            | Returns an `UploadedFile` instance array.                                                                                                                                                                   |
| `createUploadedFileFromSpec(array $value)`    | `UploadedFileInterface` or `UploadedFileInterface[]` | Creates an UploadedFile instance from a `$_FILES` specification. If the specification represents an array of values, this method will delegate to `normalizeNestedFileSpec()` and return that return value. |
| `normalizeNestedFileSpec(array $files):array` | `array`                                              | Normalizes an array of file specifications. Loops through all nested files and returns a normalized array of `UploadedFileInterface` instances.                                                             |

### Functions
The namespace `chillerlan\HTTP\Utils` contains several functions for various operations with message objects.

| function                                      | return    | info                                                                                                                                                                                                                                              |
|-----------------------------------------------|-----------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `getMimetypeFromExtension(string $extension)` | `?string` | Get the mime type for the given file extension (checks against the constant `chillerlan\HTTP\Utils\MIMETYPES`, a list of mime types from the [apache default config](http://svn.apache.org/repos/asf/httpd/httpd/branches/1.3.x/conf/mime.types)) |
| `getMimetypeFromFilename(string $filename)`   | `?string` | Get the mime type from a file name                                                                                                                                                                                                                |
| `r_rawurlencode($data)`                       | mixed     | Recursive [`rawurlencode`](https://www.php.net/manual/en/function.rawurlencode)                                                                                                                                                                   |
