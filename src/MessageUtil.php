<?php
/**
 * Class MessageUtil
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */

namespace chillerlan\HTTP\Utils;

use Psr\Http\Message\{MessageInterface, RequestInterface, ResponseInterface};
use RuntimeException, Throwable;
use function call_user_func, extension_loaded, function_exists, gzdecode, gzinflate, gzuncompress, implode,
	in_array, json_decode, json_encode, simplexml_load_string, sprintf, strtolower, trim;
use const JSON_THROW_ON_ERROR;

/**
 *
 */
final class MessageUtil{

	/**
	 * Read the message body's content
	 */
	public static function getContents(MessageInterface $message):string{
		return StreamUtil::getContents($message->getBody());
	}

	/**
	 * @throws \JsonException
	 */
	public static function decodeJSON(MessageInterface $message, bool $assoc = null):mixed{
		return json_decode(self::getContents($message), ($assoc ?? false), 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * @return \SimpleXMLElement|\stdClass|mixed
	 */
	public static function decodeXML(MessageInterface $message, bool $assoc = null):mixed{
		$data = simplexml_load_string(self::getContents($message));

		return $assoc === true
			? json_decode(json_encode($data), true) // cruel
			: $data;
	}

	/**
	 * Returns the string representation of an HTTP message. (from Guzzle)
	 */
	public static function toString(MessageInterface $message, bool $appendBody = null):string{
		$appendBody ??= true;
		$msg          = '';

		if($message instanceof RequestInterface){
			$msg = sprintf(
				'%s %s HTTP/%s',
				$message->getMethod(),
				$message->getRequestTarget(),
				$message->getProtocolVersion()
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
				$message->getReasonPhrase()
			);
		}

		foreach($message->getHeaders() as $name => $values){
			$msg .= sprintf("\r\n%s: %s", $name, implode(', ', $values));
		}

		// appending the body might cause issues in some cases, e.g. with large responses or file streams
		if($appendBody === true){
			$msg .= sprintf("\r\n\r\n%s", self::getContents($message));
		}

		return $msg;
	}

	/**
	 * Decompresses the message content according to the Content-Encoding header and returns the decompressed data
	 *
	 * @see https://github.com/kjdev/php-ext-brotli
	 * @see https://github.com/kjdev/php-ext-zstd
	 * @see https://en.wikipedia.org/wiki/HTTP_compression#Content-Encoding_tokens
	 *
	 * @throws \RuntimeException
	 */
	public static function decompress(MessageInterface $message):string{
		$data     = self::getContents($message);
		$encoding = strtolower($message->getHeaderLine('content-encoding'));

		try{
			return match($encoding){
				'', 'identity'   => $data,
				'gzip', 'x-gzip' => gzdecode($data),
				'compress'       => gzuncompress($data),
				'deflate'        => gzinflate($data),
				'br'             => self::call_decompress_func('brotli', $data),
				'zstd'           => self::call_decompress_func('zstd', $data),
			};
		}
		catch(Throwable $e){
			if(in_array($encoding, ['br', 'zstd'])){
				/** @var \RuntimeException $e */
				throw $e;
			}
		}

		throw new RuntimeException('unknown content-encoding value: '.$encoding);
	}

	/**
	 *
	 */
	protected static function call_decompress_func(string $func, string $data):string{
		$fn = $func.'_uncompress';

		if(!extension_loaded($func) || !function_exists($fn)){
			throw new RuntimeException(sprintf('cannot decompress %s compressed message body', $func));
		}

		return call_user_func($fn, $data);
	}

	/**
	 * Sets a Content-Length header in the given message in case it does not exist and body size is not null
	 */
	public static function setContentLengthHeader(MessageInterface $message):MessageInterface{
		$bodySize = $message->getBody()->getSize();

		if(!$message->hasHeader('Content-Length') && $bodySize !== null){
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
		string           $filename = null,
		string           $extension = null
	):MessageInterface{
		$mime = (
			   MimeTypeUtil::getFromExtension(trim(($extension ?? ''), ".\t\n\r\0\x0B"))
			?? MimeTypeUtil::getFromFilename(($filename ?? ''))
			?? MimeTypeUtil::getFromContent(self::getContents($message))
		);

		if($mime === null){
			throw new RuntimeException('could not determine content type');
		}

		return $message->withHeader('Content-Type', $mime);
	}

}
