<?php
/**
 * Class MessageUtilTest
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\MessageUtil;
use RuntimeException;
use function extension_loaded, function_exists, str_repeat;

/**
 *
 */
class MessageUtilTest extends TestAbstract{

	public function testGetJSON():void{

		$r = $this->responseFactory->createResponse()->withBody($this->streamFactory->createStream('{"foo":"bar"}'));

		$this::assertSame('bar', MessageUtil::decodeJSON($r)->foo);

		$r->getBody()->rewind();

		$this::assertSame('bar', MessageUtil::decodeJSON($r, true)['foo']);
	}

	public function testGetXML():void{

		$r = $this->responseFactory
			->createResponse()
			->withBody($this->streamFactory->createStream('<?xml version="1.0" encoding="UTF-8"?><root><foo>bar</foo></root>'));

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
			MessageUtil::toString($request)
		);

		$response = $this->responseFactory
			->createResponse()
			->withAddedHeader('foo', 'bar')
			->withBody($body)
		;

		$this::assertSame(
			'HTTP/1.1 200 OK'."\r\n".'foo: bar'."\r\n\r\n".'testbody',
			MessageUtil::toString($response)
		);
	}

	public function decompressDataProvider():array{
		return [
			'br'       => ['brotli_compress', 'br'],
			'zstd'     => ['zstd_compress', 'zstd'],
			'compress' => ['gzcompress', 'compress'],
			'deflate'  => ['gzdeflate', 'deflate'],
			'gzip'     => ['gzencode', 'gzip'],
			'none'     => ['', ''],
		];
	}

	/**
	 * @dataProvider decompressDataProvider
	 */
	public function testDecompressContent(string $fn, string $encoding):void{

		if($encoding === 'br' && (!extension_loaded('brotli') || !function_exists('brotli_compress'))){
			$this::markTestSkipped('N/A (ext-brotli not installed)');
		}

		if($encoding === 'zstd' && (!extension_loaded('zstd') || !function_exists('zstd_compress'))){
			$this::markTestSkipped('N/A (ext-zstd not installed)');
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

	public function testDecompressContentUnableToDecompressBrotliException():void{

		if(extension_loaded('brotli') && function_exists('brotli_uncompress')){
			$this::markTestSkipped('N/A (ext-brotli isntalled)');
		}

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('cannot decompress brotli compressed message body');

		$response = $this->responseFactory
			->createResponse()
			->withHeader('Content-Encoding', 'br');

		MessageUtil::decompress($response);
	}

	public function testDecompressContentUnableToDecompressZstdException():void{

		if(extension_loaded('zstd') && function_exists('zstd_uncompress')){
			$this::markTestSkipped('N/A (ext-zstd isntalled)');
		}

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('cannot decompress zstd compressed message body');

		$response = $this->responseFactory
			->createResponse()
			->withHeader('Content-Encoding', 'zstd');

		MessageUtil::decompress($response);
	}

}
