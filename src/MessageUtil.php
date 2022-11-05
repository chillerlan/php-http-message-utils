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
use RuntimeException;
use function extension_loaded, function_exists, gzdecode, gzinflate, gzuncompress, implode, json_decode, json_encode,
	simplexml_load_string, strtolower, trim;

/**
 *
 */
class MessageUtil{

	/**
	 * Read the message body's content and make sure we rewind
	 */
	protected static function getContents(MessageInterface $message):string{
		$body = $message->getBody();
		$body->rewind(); //rewind before read...
		$data = $body->getContents();
		$body->rewind(); // ...and after

		return $data;
	}

	/**
	 * @return \stdClass|array|bool
	 */
	public static function decodeJSON(MessageInterface $message, bool $assoc = null){
		return json_decode(self::getContents($message), $assoc ?? false);
	}

	/**
	 * @return \SimpleXMLElement|array|bool
	 */
	public static function decodeXML(MessageInterface $message, bool $assoc = null){
		$data = simplexml_load_string(self::getContents($message));

		return $assoc === true
			? json_decode(json_encode($data), true) // cruel
			: $data;
	}

	/**
	 * Returns the string representation of an HTTP message. (from Guzzle)
	 */
	public static function toString(MessageInterface $message, bool $appendBody = true):string{
		$msg = '';

		if($message instanceof RequestInterface){
			$msg = trim($message->getMethod().' '.$message->getRequestTarget()).' HTTP/'.$message->getProtocolVersion();

			if(!$message->hasHeader('host')){
				$msg .= "\r\nHost: ".$message->getUri()->getHost();
			}

		}
		elseif($message instanceof ResponseInterface){
			$msg = 'HTTP/'.$message->getProtocolVersion().' '.$message->getStatusCode().' '.$message->getReasonPhrase();
		}

		foreach($message->getHeaders() as $name => $values){
			$msg .= "\r\n".$name.': '.implode(', ', $values);
		}

		// appending the body might cause issues in some cases, e.g. with large responses or file streams
		if($appendBody){
			$msg .= "\r\n\r\n".self::getContents($message);
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

		if($encoding === '' || $encoding === 'identity'){
			return $data;
		}

		if($encoding === 'gzip' || $encoding === 'x-gzip'){
			return gzdecode($data);
		}

		if($encoding === 'compress'){
			return gzuncompress($data);
		}

		if($encoding === 'deflate'){
			return gzinflate($data);
		}

		if($encoding === 'br'){

			if(extension_loaded('brotli') && function_exists('brotli_uncompress')){
				/** @phan-suppress-next-line PhanUndeclaredFunction */
				return \brotli_uncompress($data); // @codeCoverageIgnore
			}

			throw new RuntimeException('cannot decompress brotli compressed message body');
		}

		if($encoding === 'zstd'){

			if(extension_loaded('zstd') && function_exists('zstd_uncompress')){
				/** @phan-suppress-next-line PhanUndeclaredFunction */
				return \zstd_uncompress($data); // @codeCoverageIgnore
			}

			throw new RuntimeException('cannot decompress zstd compressed message body');
		}

		throw new RuntimeException('unknown content-encoding value: '.$encoding);
	}

}
