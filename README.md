# chillerlan/php-http-message-utils

A collection of framework-agnostic utilities for use with [PSR-7 Message implementations](https://www.php-fig.org/psr/psr-7/).

[![PHP Version Support][php-badge]][php]
[![version][packagist-badge]][packagist]
[![license][license-badge]][license]
[![Continuous Integration][gh-action-badge]][gh-action]
[![Coverage][coverage-badge]][coverage]
[![Codacy][codacy-badge]][codacy]
[![Packagist downloads][downloads-badge]][downloads]

[php-badge]: https://img.shields.io/packagist/php-v/chillerlan/php-http-message-utils?logo=php&color=8892BF
[php]: https://www.php.net/supported-versions.php
[packagist-badge]: https://img.shields.io/packagist/v/chillerlan/php-http-message-utils?logo=packagist
[packagist]: https://packagist.org/packages/chillerlan/php-http-message-utils
[license-badge]: https://img.shields.io/github/license/chillerlan/php-http-message-utils
[license]: https://github.com/chillerlan/php-http-message-utils/blob/main/LICENSE
[coverage-badge]: https://img.shields.io/codecov/c/github/chillerlan/php-http-message-utils?logo=codecov
[coverage]: https://codecov.io/github/chillerlan/php-http-message-utils
[codacy-badge]: https://img.shields.io/codacy/grade/70e19515c2734e0a9036d83dbbd1469c?logo=codacy
[codacy]: https://app.codacy.com/gh/chillerlan/php-http-message-utils/dashboard
[downloads-badge]: https://img.shields.io/packagist/dt/chillerlan/php-http-message-utils?logo=packagist
[downloads]: https://packagist.org/packages/chillerlan/php-http-message-utils/stats
[gh-action-badge]: https://img.shields.io/github/actions/workflow/status/chillerlan/php-http-message-utils/ci.yml?branch=main&logo=github
[gh-action]: https://github.com/chillerlan/php-http-message-utils/actions/workflows/ci.yml?query=branch%3Amain


# Documentation

## Requirements
  - PHP 8.1+
    - `ext-fileinfo`, `ext-intl`, `ext-json`, `ext-mbstring`, `ext-simplexml`, `ext-zlib`
    - for `MessageUtil::decompress()`: `ext-br` [kjdev/php-ext-brotli](https://github.com/kjdev/php-ext-brotli) or `ext-zstd` [kjdev/php-ext-zstd](https://github.com/kjdev/php-ext-zstd)

## Installation

**requires [composer](https://getcomposer.org)**

`composer.json` (note: replace `dev-main` with a [version boundary](https://getcomposer.org/doc/articles/versions.md), e.g. `^2.2`)
```json
{
	"require": {
		"php": "^8.1",
		"chillerlan/php-http-message-utils": "dev-main#<commit_hash>"
	}
}
```
Profit!


## Usage

### `URLExtractor`

The `URLExtractor` wraps a PSR-18 `ClientInterface` to extract and follow shortened URLs to their original location.

```php
// @see https://github.com/chillerlan/php-httpinterface
$options                 = new HTTPOptions;
$options->user_agent     = 'my cool user agent 1.0';
$options->ssl_verifypeer = false;
$options->curl_options   = [
	CURLOPT_FOLLOWLOCATION => false,
	CURLOPT_MAXREDIRS      => 25,
];

$httpClient   = new CurlClient($responseFactory, $options, $logger);
$urlExtractor = new URLExtractor($httpClient, $responseFactory);

$request = $factory->createRequest('GET', 'https://t.co/ZSS6nVOcVp');

$urlExtractor->sendRequest($request); // -> response from the final location

// you can retrieve an array with all followed locations afterwards
$responses = $urlExtractor->getResponses(); // -> ResponseInterface[]

// if you just want the URL of the final location, you can use the extract method:
$url = $urlExtractor->extract('https://t.co/ZSS6nVOcVp'); // -> https://api.guildwars2.com/v2/build
```

### `EchoClient`

The `EchoClient` returns a JSON representation the original message:

```php
$echoClient = new EchoClient($responseFactory);

$request  = $requestFactory->createRequest('GET', 'https://example.com?whatever=value');
$response = $echoClient->sendRequest($request);
$json     = json_decode($response->getBody()->getContents());
```

Which yields an object similar to the following

```json
{
	"headers": {
		"Host": "example.com"
	},
	"request": {
		"url": "https://example.com?whatever=value",
		"params": {
			"whatever": "value"
		},
		"method": "GET",
		"target": "/",
		"http": "1.1"
	},
	"body": ""
}
```


### `LoggingClient`

The `LoggingClient` wraps a `ClientInterface` and outputs the HTTP messages in a readable way through a `LoggerInterface` (do NOT use in production!).

```php
$loggingClient = new LoggingClient($httpClient, $logger);

$loggingClient->sendRequest($request); // -> log to output given via logger
```

The output looks similar to the following (using [monolog](https://github.com/Seldaek/monolog)):

```
[2024-03-15 22:10:41][debug] LoggingClientTest:
----HTTP-REQUEST----
GET /get HTTP/1.1
Host: httpbin.org


[2024-03-15 22:10:41][debug] LoggingClientTest:
----HTTP-RESPONSE---
HTTP/1.1 200 OK
Date: Fri, 15 Mar 2024 21:10:40 GMT
Content-Type: application/json
Content-Length: 294
Connection: keep-alive
Server: gunicorn/19.9.0
Access-Control-Allow-Origin: *
Access-Control-Allow-Credentials: true

{
  "args": {},
  "headers": {
    "Host": "httpbin.org",
    "User-Agent": "chillerlanPHPUnitHttp/1.0.0 +https://github.com/chillerlan/phpunit-http",
    "X-Amzn-Trace-Id": "Root=1-65f4b950-1f87b9e37182673438091aea"
  },
  "origin": "93.236.207.163",
  "url": "https://httpbin.org/get"
}
```


## API
The following classes contain static methods for use with PSR-7 http message objects.

### `HeaderUtil`
| method                              | return   | info                                                                                                                                                                                                                                                                     |
|-------------------------------------|----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `normalize(array $headers)`         | `array`  | Normalizes an array of header lines to format `["Name" => "Value (, Value2, Value3, ...)", ...]` An exception is being made for `Set-Cookie`, which holds an array of values for each cookie. For multiple cookies with the same name, only the last value will be kept. |
| `trimValues(array $values)`         | `array`  | Trims whitespace from the header values                                                                                                                                                                                                                                  |
| `normalizeHeaderName(string $name)` | `string` | Normalizes a header name, e.g. "conTENT- lenGTh" -> "Content-Length"                                                                                                                                                                                                     |

### `QueryUtil`
| method                                                                                           | return          | info                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
|--------------------------------------------------------------------------------------------------|-----------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `cleanParams(iterable $params, int $bool_cast = null, bool $remove_empty = true)`                | `array`         | Cleans/normalizes an array of query parameters, booleans will be converted according to the given `$bool_cast` constant. By default, booleans will be left as-is (`Query::BOOLEANS_AS_BOOL`) and may result in empty values. If `$remove_empty` is set to true, empty non-boolean and null values will be removed from the array. The `Query` class provides the following constants for `$bool_cast`:<br>`BOOLEANS_AS_BOOL`: unchanged boolean value (default)<br>`BOOLEANS_AS_INT`: integer values 0 or 1<br>`BOOLEANS_AS_STRING`: "true"/"false" strings<br>`BOOLEANS_AS_INT_STRING`: "0"/"1" strings |
| `build(array $params, int $encoding = null, string $delimiter = null, string $enclosure = null)` | `string`        | Builds a query string from an array of key value pairs, similar to [`http_build_query`](https://www.php.net/manual/en/function.http-build-query). Valid values for `$encoding` are `PHP_QUERY_RFC3986` (default) and `PHP_QUERY_RFC1738`, any other integer value will be interpreted as "no encoding" (`Query::NO_ENCODING`).                                                                                                                                                                                                                                                                           |
| `merge(string $uri, array $query)`                                                               | `string`        | Merges additional query parameters into an existing query string.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `parse(string $querystring, int $urlEncoding = null)`                                            | `array`         | Parses a query string into an associative array, similar to [`parse_str`](https://www.php.net/manual/en/function.parse-str) (without the inconvenient usage of a by-reference result variable).                                                                                                                                                                                                                                                                                                                                                                                                          |
| `recursiveRawurlencode(mixed $data)`                                                             | `array\|string` | Recursive [`rawurlencode`](https://www.php.net/manual/en/function.rawurlencode)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |

### `MessageUtil`
| method                                                                                                                                                                                                                                              | return              | info                                                                                                                                                                                                                                                  |
|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `getContents(MessageInterface $message)`                                                                                                                                                                                                            | `string`            | Reads the body content of a `MessageInterface` and makes sure to rewind.                                                                                                                                                                              |
| `decodeJSON(MessageInterface $message, bool $assoc = false)`                                                                                                                                                                                        | mixed               | fetches the body of a `MessageInterface` and converts it to a JSON object (`stdClass`) or an associative array if `$assoc` is set to `true` and returns the result.                                                                                   |
| `decodeXML(MessageInterface $message, bool $assoc = false)`                                                                                                                                                                                         | mixed               | fetches the body of a `MessageInterface` and converts it to a `SimpleXMLElement` or an associative array if `$assoc` is set to `true` and returns the result.                                                                                         |
| `toString(MessageInterface $message, bool $appendBody = true)`                                                                                                                                                                                      | `string`            | Returns the string representation of an HTTP message.                                                                                                                                                                                                 |
| `toJSON(MessageInterface $message, bool $appendBody = true)`                                                                                                                                                                                        | `string`            | Returns the string representation of an HTTP message.                                                                                                                                                                                                 |
| `decompress(MessageInterface $message)`                                                                                                                                                                                                             | `string`            | Decompresses the message content according to the `Content-Encoding` header (`compress`, `deflate`, `gzip`, `br`, `zstd`) and returns the decompressed data. `br` and `zstd` will throw a `RuntimeException` if the respecive extensions are missing. |
| `setContentLengthHeader(MessageInterface $message)`                                                                                                                                                                                                 | `MessageInterface`  | Sets a Content-Length header in the given message in case it does not exist and body size is not null                                                                                                                                                 |
| `setContentTypeHeader(MessageInterface $message, string $filename = null, string $extension = null)`                                                                                                                                                | `MessageInterface`  | Tries to determine the content type from the given values and sets the Content-Type header accordingly, throws if no mime type could be guessed.                                                                                                      |
| `setCookie(ResponseInterface $message, string $name, string $value = null, DateTimeInterface\|DateInterval\|int $expiry = null, string $domain = null, string $path = null, bool $secure = false, bool $httpOnly = false, string $sameSite = null)` | `ResponseInterface` | Adds a Set-Cookie header to a ResponseInterface (convenience)                                                                                                                                                                                         |
| `getCookiesFromHeader(MessageInterface $message)`                                                                                                                                                                                                   | `array\|null`       | Attempts to extract and parse a cookie from a "Cookie" (user-agent) header                                                                                                                                                                            |

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
| `parseUrl(string $url)`                                                | `?array`       | UTF-8 aware `\parse_url()` replacement.                                                                                                                                                                                                                                     |

### `MimeTypeUtil`
| method                                | return    | info                                                                                                                                                                                                                                              |
|---------------------------------------|-----------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `getFromExtension(string $extension)` | `?string` | Get the mime type for the given file extension (checks against the constant `chillerlan\HTTP\Utils\MIMETYPES`, a list of mime types from the [apache default config](http://svn.apache.org/repos/asf/httpd/httpd/branches/1.3.x/conf/mime.types)) |
| `getFromFilename(string $filename)`   | `?string` | Get the mime type from a file name                                                                                                                                                                                                                |
| `getFromContent(string $content)`     | `?string` | Get the mime type from the given content                                                                                                                                                                                                          |

### `StreamUtil`
| method                                                                                       | return     | info                                                                                                                                                |
|----------------------------------------------------------------------------------------------|------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| `getContents(string $extension)`                                                             | `string`   | Reads the content from a stream and make sure we rewind                                                                                             |
| `copyToStream(StreamInterface $source, StreamInterface $destination, int $maxLength = null)` | `int`      | Copies a stream to another stream, starting from the current position of the source stream, reading to the end or until the given maxlength is hit. |
| `tryFopen(string $filename, string $mode, $context = null)`                                  | `resource` | Safely open a PHP resource, throws instead of raising warnings and errors                                                                           |
| `tryGetContents($stream, int $length = null, int $offset = -1)`                              | `string`   | Safely get the contents of a stream resource, throws instead of raising warnings and errors                                                         |
| `validateMode(string $mode)`                                                                 | `string`   | Checks if the given mode is valid for `fopen()`                                                                                                     |
| `modeAllowsReadWrite(string $mode)`                                                          | `bool`     | Checks whether the given mode allows reading and writing                                                                                            |
| `modeAllowsReadOnly(string $mode)`                                                           | `bool`     | Checks whether the given mode allows only reading                                                                                                   |
| `modeAllowsWriteOnly(string $mode)`                                                          | `bool`     | Checks whether the given mode allows only writing                                                                                                   |
| `modeAllowsRead(string $mode)`                                                               | `bool`     | Checks whether the given mode allows reading                                                                                                        |
| `modeAllowsWrite(string $mode)`                                                              | `bool`     | Checks whether the given mode allows writing                                                                                                        |

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

### `Cookie`
Implements a [HTTP cookie](https://datatracker.ietf.org/doc/html/rfc6265#section-4.1)

| method                                                           | return   | info                                                           |
|------------------------------------------------------------------|----------|----------------------------------------------------------------|
| `__construct(string $name, string $value = null)`                | -        |                                                                |
| `__toString()`                                                   | `string` | returns the full cookie string to use in a `Set-Cookie` header |
| `withNameAndValue(string $name, string $value)`                  | `Cookie` |                                                                |
| `withExpiry(DateTimeInterface\|DateInterval\|int\|null $expiry)` | `Cookie` |                                                                |
| `withDomain(string\|null $domain, bool $punycode = null)`        | `Cookie` |                                                                |
| `withPath(string\|null $path)`                                   | `Cookie` |                                                                |
| `withSecure(bool $secure)`                                       | `Cookie` |                                                                |
| `withHttpOnly(bool $httpOnly)`                                   | `Cookie` |                                                                |
| `withSameSite(string\|null $sameSite)`                           | `Cookie` |                                                                |
