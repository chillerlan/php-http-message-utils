<?php
/**
 * Class MessageUtil
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTP\Utils;

use Psr\Http\Message\{MessageInterface, RequestInterface, ResponseInterface, ServerRequestInterface};
use DateInterval, DateTimeInterface, RuntimeException, Throwable;
use function array_map, explode, extension_loaded, function_exists, gzdecode, gzinflate, gzuncompress, implode,
	in_array, json_decode, json_encode, rawurldecode, simplexml_load_string, sprintf, strtolower, trim;
use const JSON_PRETTY_PRINT, JSON_THROW_ON_ERROR, JSON_UNESCAPED_SLASHES;

final class MessageUtil{

	/**
	 * Read the message body's content, throws if the content could not be read from the message body
	 *
	 * @throws \RuntimeException
	 */
	public static function getContents(MessageInterface $message):string{
		$content = StreamUtil::getContents($message->getBody());

		if($content === null){
			throw new RuntimeException('invalid message content'); // @codeCoverageIgnore
		}

		return $content;
	}

	/**
	 * @throws \JsonException
	 */
	public static function decodeJSON(MessageInterface $message, bool|null $assoc = null):mixed{
		return json_decode(self::decompress($message), ($assoc ?? false), 512, JSON_THROW_ON_ERROR);
	}

	public static function decodeXML(MessageInterface $message, bool|null $assoc = null):mixed{
		$data = simplexml_load_string(self::decompress($message));

		if($assoc === true){
			return json_decode(json_encode($data), true); // cruel
		}

		return  $data;
	}

	/**
	 * Returns the string representation of an HTTP message. (from Guzzle)
	 */
	public static function toString(MessageInterface $message, bool|null $appendBody = null):string{
		$appendBody ??= true;
		$msg          = '';

		if($message instanceof RequestInterface){
			$msg = sprintf(
				'%s %s HTTP/%s',
				$message->getMethod(),
				$message->getRequestTarget(),
				$message->getProtocolVersion(),
			);

			if(!$message->hasHeader('host')){
				$msg .= sprintf("\r\nHost: %s", $message->getUri()->getHost());
			}

		}
		elseif($message instanceof ResponseInterface){
			$msg = sprintf(
				'HTTP/%s %s %s',
				$message->getProtocolVersion(),
				$message->getStatusCode(),
				$message->getReasonPhrase(),
			);
		}

		foreach($message->getHeaders() as $name => $values){
			$msg .= sprintf("\r\n%s: %s", $name, implode(', ', $values));
		}

		// appending the body might cause issues in some cases, e.g. with large responses or file streams
		if($appendBody === true){
			$msg .= sprintf("\r\n\r\n%s", self::decompress($message));
		}

		return $msg;
	}

	/**
	 * Returns a JSON representation of an HTTP message.
	 */
	public static function toJSON(MessageInterface $message, bool|null $appendBody = null):string{
		$appendBody ??= true;
		$msg          = ['headers' => []];

		if($message instanceof RequestInterface){
			$uri = $message->getUri();

			$msg['request'] = [
				'url'    => (string)$uri,
				'params' => QueryUtil::parse($uri->getQuery()),
				'method' => $message->getMethod(),
				'target' => $message->getRequestTarget(),
				'http'   => $message->getProtocolVersion(),
			];

			if(!$message->hasHeader('host')){
				$msg['headers']['Host'] = $message->getUri()->getHost();
			}

		}
		elseif($message instanceof ResponseInterface){
			$msg['response'] = [
				'status' => $message->getStatusCode(),
				'reason' => $message->getReasonPhrase(),
				'http'   => $message->getProtocolVersion(),
			];
		}

		foreach($message->getHeaders() as $name => $values){
			$msg['headers'][$name] = implode(', ', $values);
		}

		if($appendBody === true){
			$msg['body'] = self::decompress($message);
		}

		return json_encode($msg, (JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * Decompresses the message content according to the Content-Encoding header and returns the decompressed data
	 *
	 * @see https://github.com/kjdev/php-ext-brotli
	 * @see https://github.com/kjdev/php-ext-zstd
	 * @see https://en.wikipedia.org/wiki/HTTP_compression#Content-Encoding_tokens
	 *
	 * @throws \Throwable|\RuntimeException
	 */
	public static function decompress(MessageInterface $message):string{
		$data     = self::getContents($message);
		$encoding = strtolower($message->getHeaderLine('content-encoding'));

		try{
			$decoded = match($encoding){
				'', 'identity'   => $data,
				'gzip', 'x-gzip' => gzdecode($data),
				'compress'       => gzuncompress($data),
				'deflate'        => gzinflate($data),
				'br'             => self::call_decompress_func('brotli', $data),
				'zstd'           => self::call_decompress_func('zstd', $data),
			};

			if($decoded === false){
				throw new RuntimeException;
			}

			return $decoded;
		}
		catch(Throwable $e){
			if(in_array($encoding, ['br', 'zstd'], true)){
				throw $e;
			}
		}

		throw new RuntimeException('unknown content-encoding value: '.$encoding);
	}

	/**
	 * @codeCoverageIgnore
	 */
	protected static function call_decompress_func(string $func, string $data):string{
		$fn = $func.'_uncompress';

		if(!extension_loaded($func) || !function_exists($fn)){
			throw new RuntimeException(sprintf('cannot decompress %s compressed message body', $func));
		}

		return $fn($data);
	}

	/**
	 * Sets a Content-Length header in the given message in case it does not exist and body size is not null
	 */
	public static function setContentLengthHeader(
		MessageInterface $message,
	):MessageInterface|RequestInterface|ResponseInterface|ServerRequestInterface{
		$bodySize = $message->getBody()->getSize();

		if(!$message->hasHeader('Content-Length') && $bodySize !== null && $bodySize > 0){
			$message = $message->withHeader('Content-Length', (string)$bodySize);
		}

		return $message;
	}

	/**
	 * Tries to determine the content type from the given values and sets the Content-Type header accordingly,
	 * throws if no mime type could be guessed.
	 *
	 * @throws \RuntimeException
	 */
	public static function setContentTypeHeader(
		MessageInterface $message,
		string|null      $filename = null,
		string|null      $extension = null,
	):MessageInterface|RequestInterface|ResponseInterface|ServerRequestInterface{
		$mime = (
			   MimeTypeUtil::getFromExtension(trim(($extension ?? ''), ".\t\n\r\0\x0B"))
			?? MimeTypeUtil::getFromFilename(($filename ?? ''))
			?? MimeTypeUtil::getFromContent(self::getContents($message))
		);

		if($mime === null){
			throw new RuntimeException('could not determine content type'); // @codeCoverageIgnore
		}

		return $message->withHeader('Content-Type', $mime);
	}

	/**
	 * Adds a Set-Cookie header to a ResponseInterface (convenience)
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie
	 */
	public static function setCookie(
		ResponseInterface                       $message,
		string                                  $name,
		string|null                             $value = null,
		DateTimeInterface|DateInterval|int|null $expiry = null,
		string|null                             $domain = null,
		string|null                             $path = null,
		bool                                    $secure = false,
		bool                                    $httpOnly = false,
		string|null                             $sameSite = null,
	):ResponseInterface{

		$cookie = (new Cookie($name, $value))
			->withExpiry($expiry)
			->withDomain($domain)
			->withPath($path)
			->withSecure($secure)
			->withHttpOnly($httpOnly)
			->withSameSite($sameSite)
		;

		return $message->withAddedHeader('Set-Cookie', (string)$cookie);
	}

	/**
	 * Attempts to extract and parse a cookie from a "Cookie" (user-agent) header
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cookie
	 *
	 * @return array<string, string>|null
	 */
	public static function getCookiesFromHeader(MessageInterface $message):array|null{

		if(!$message->hasHeader('Cookie')){
			return null;
		}

		$header = trim($message->getHeaderLine('Cookie'));

		if(empty($header)){
			return null;
		}

		$cookies = [];

		// some people apparently use regular expressions for this (:
		foreach(array_map(trim(...), explode(';', $header)) as $kv){
			[$name, $value] = array_map(trim(...), explode('=', $kv, 2));

			$cookies[$name] = rawurldecode($value);
		}

		return $cookies;
	}

}
