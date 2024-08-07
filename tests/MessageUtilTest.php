<?php
/**
 * Class MessageUtilTest
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\MessageUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use function extension_loaded, file_get_contents, function_exists, json_decode, sprintf, str_repeat;

final class MessageUtilTest extends UtilTestAbstract{

	public function testGetJSON():void{

		$r = $this->responseFactory
			->createResponse()
			->withBody($this->streamFactory->createStream('{"foo":"bar"}'))
		;

		$this::assertSame('bar', MessageUtil::decodeJSON($r)->foo);

		$r->getBody()->rewind();

		$this::assertSame('bar', MessageUtil::decodeJSON($r, true)['foo']);
	}

	public function testGetXML():void{

		$r = $this->responseFactory
			->createResponse()
			->withBody($this->streamFactory->createStream('<?xml version="1.0" encoding="UTF-8"?><root><foo>bar</foo></root>'))
		;

		$this::assertSame('bar', MessageUtil::decodeXML($r)->foo->__toString());

		$r->getBody()->rewind();

		$this::assertSame('bar', MessageUtil::decodeXML($r, true)['foo']);
	}

	public function testMessageToString():void{
		$body = $this->streamFactory->createStream('testbody');

		$request = $this->requestFactory
			->createRequest('GET', 'https://localhost/foo')
			->withAddedHeader('foo', 'bar')
			->withBody($body)
		;

		$this::assertSame(
			'GET /foo HTTP/1.1'."\r\n".'Host: localhost'."\r\n".'foo: bar'."\r\n\r\n".'testbody',
			MessageUtil::toString($request),
		);

		$response = $this->responseFactory
			->createResponse()
			->withAddedHeader('foo', 'bar')
			->withBody($body)
		;

		$this::assertSame(
			'HTTP/1.1 200 OK'."\r\n".'foo: bar'."\r\n\r\n".'testbody',
			MessageUtil::toString($response),
		);
	}

	public function testMessageToJSON():void{
		$body = $this->streamFactory->createStream('testbody');

		$request = $this->requestFactory
			->createRequest('GET', 'https://localhost/foo')
			->withAddedHeader('foo', 'bar')
			->withBody($body)
		;

		$json = json_decode(MessageUtil::toJSON($request));

		$this::assertSame('GET', $json->request->method);
		$this::assertSame('localhost', $json->headers->{'Host'});
		$this::assertSame('testbody', $json->body);


		$response = $this->responseFactory
			->createResponse()
			->withAddedHeader('foo', 'bar')
			->withBody($body)
		;

		$json = json_decode(MessageUtil::toJSON($response));

		$this::assertSame(200, $json->response->status);
		$this::assertSame('bar', $json->headers->{'foo'});
		$this::assertSame('testbody', $json->body);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function decompressFnProvider():array{
		return [
			'br'       => ['brotli_compress', 'br'],
			'zstd'     => ['zstd_compress', 'zstd'],
			'compress' => ['gzcompress', 'compress'],
			'deflate'  => ['gzdeflate', 'deflate'],
			'gzip'     => ['gzencode', 'gzip'],
			'x-gzip'   => ['gzencode', 'x-gzip'],
			'none'     => ['', ''],
		];
	}

	#[DataProvider('decompressFnProvider')]
	public function testDecompressContent(string $fn, string $encoding):void{

		if(!empty($fn) && !function_exists($fn)){
			$this::markTestSkipped(sprintf('N/A function "%s" does not exist (extension not installed?)', $fn));
		}

		$data     = str_repeat('compressed string ', 100);
		$expected = $data;
		$response = $this->responseFactory->createResponse();

		if($fn){
			$data     = $fn($data);
			$response = $response->withHeader('Content-Encoding', $encoding);
		}

		$response = $response->withBody($this->streamFactory->createStream($data));

		$this::assertSame($expected, MessageUtil::decompress($response));
	}

	public function testDecompressContentUnknownHeaderValueException():void{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('unknown content-encoding value: foo');

		$response = $this->responseFactory
			->createResponse()
			->withHeader('Content-Encoding', 'foo');

		MessageUtil::decompress($response);
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function decompressExceptionFnProvider():array{
		return [
			'br'   => ['brotli', 'brotli_compress', 'br'],
			'zstd' => ['zstd', 'zstd_compress', 'zstd'],
		];
	}

	#[DataProvider('decompressExceptionFnProvider')]
	public function testDecompressContentUnableToDecompressException(string $ext, string $fn, string $encoding):void{

		if(extension_loaded($ext) && function_exists($fn)){
			$this::markTestSkipped(sprintf('N/A (ext-%s installed)', $ext));
		}

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(sprintf('cannot decompress %s compressed message body', $ext));

		$response = $this->responseFactory
			->createResponse()
			->withHeader('Content-Encoding', $encoding);

		MessageUtil::decompress($response);
	}

	public function testSetContentLengthHeader():void{
		$message = $this->requestFactory->createRequest('GET', 'https://example.com');
		$message->getBody()->write('foo');

		$this::assertFalse($message->hasHeader('Content-Length'));

		$message = MessageUtil::setContentLengthHeader($message);

		$this::assertTrue($message->hasHeader('Content-Length'));
		$this->assertSame(
			(string)$message->getBody()->getSize(),
			$message->getHeaderLine('Content-Length'),
		);
	}

	/**
	 * @return array<string, array{0: string, 1: string|null, 2: string|null, 3: string}>
	 */
	public static function contentTypeProvider():array{
		return [
			'text/plain'             => ['foo', null, null, 'text/plain'],
			'application/json'       => ['{}', null, null, 'application/json'],
			'text/javascript (name)' => ['{}', 'test.js', null, 'text/javascript'],
			'text/javascript (ext)'  => ['{}', null, 'js', 'text/javascript'],
			'text/x-php'             => [file_get_contents(__FILE__), null, null, 'text/x-php'],
			'text/markdown'          => [file_get_contents(__DIR__.'/../README.md'), null, 'md', 'text/markdown'],
		];
	}

	#[DataProvider('contentTypeProvider')]
	public function testSetContentTypeHeader(
		string      $content,
		string|null $filename,
		string|null $extension,
		string      $expectedMIME,
	):void{

		$message = $this->requestFactory
			->createRequest('GET', 'https://example.com')
			->withBody($this->streamFactory->createStream($content))
		;

		$message = MessageUtil::setContentTypeHeader($message, $filename, $extension);

		$this::assertTrue($message->hasHeader('content-type'));
		$this::assertSame($expectedMIME, $message->getHeaderLine('content-type'));
	}

	public function testSetCookie():void{

		$response = MessageUtil::setCookie(
			$this->responseFactory->createResponse(), 'foo', 'what', null, 'example.com', '/', true, true, 'strict',
		);

		$cookieHeaders = $response->getHeader('Set-Cookie');

		$this::assertSame(
			'foo=what; Domain=example.com; Path=/; Secure; HttpOnly; SameSite=strict',
			$cookieHeaders[0],
		);
	}

	public function testGetCookieFromHeader():void{
		$response = $this->responseFactory->createResponse();

		$this::assertNull(MessageUtil::getCookiesFromHeader($response));
		$this::assertNull(MessageUtil::getCookiesFromHeader($response->withHeader('Cookie', ' ')));

		$response = $this->responseFactory
			->createResponse()
			->withHeader('Cookie', 'name1=value1; name2=value2; empty=;foo=bar')
		;

		$expected = [
			'name1' => 'value1',
			'name2' => 'value2',
			'empty' => '',
			'foo'   => 'bar',
		];

		$this::assertSame($expected, MessageUtil::getCookiesFromHeader($response));
	}
}
