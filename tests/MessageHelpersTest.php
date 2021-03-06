<?php
/**
 * Class MessageHelpersTest
 *
 * @created      01.09.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils;

use RuntimeException, TypeError;

use function chillerlan\HTTP\Utils\{
	decompress_content, get_json, get_xml, getMimetypeFromExtension, getMimetypeFromFilename, message_to_string,
	parseUrl, r_rawurlencode, uriIsAbsolute, uriIsAbsolutePathReference, uriIsDefaultPort, uriIsNetworkPathReference,
	uriIsRelativePathReference, uriWithoutQueryValue, uriWithQueryValue
};

use function extension_loaded, function_exists;
use const chillerlan\HTTP\Utils\URI_DEFAULT_PORTS;

class MessageHelpersTest extends TestAbstract{

	public function rawurlencodeDataProvider():array{
		return [
			'null'         => [null, ''],
			'bool (false)' => [false, ''],
			'bool (true)'  => [true, '1'],
			'int'          => [42, '42'],
			'float'        => [42.42, '42.42'],
			'string'       => ['some test string!', 'some%20test%20string%21'],
			'array'        => [
				['some other', 'test string', ['oh wait!', 'this', ['is an', 'array!']]],
				['some%20other', 'test%20string', ['oh%20wait%21', 'this', ['is%20an', 'array%21']]],
			],
		];
	}

	/**
	 * @dataProvider rawurlencodeDataProvider
	 *
	 * @param $data
	 * @param $expected
	 */
	public function testRawurlencode($data, $expected):void{
		$this::assertSame($expected, r_rawurlencode($data));
	}

	public function testRawurlencodeTypeErrorException():void{
		$this->expectException(TypeError::class);

		r_rawurlencode((object)[]);
	}

	public function testGetJSON():void{

		$r = $this->responseFactory->createResponse()->withBody($this->streamFactory->createStream('{"foo":"bar"}'));

		$this::assertSame('bar', get_json($r)->foo);

		$r->getBody()->rewind();

		$this::assertSame('bar', get_json($r, true)['foo']);
	}

	public function testGetXML():void{

		$r = $this->responseFactory
			->createResponse()
			->withBody($this->streamFactory->createStream('<?xml version="1.0" encoding="UTF-8"?><root><foo>bar</foo></root>'));

		$this::assertSame('bar', get_xml($r)->foo->__toString());

		$r->getBody()->rewind();

		$this::assertSame('bar', get_xml($r, true)['foo']);
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
			message_to_string($request)
		);

		$response = $this->responseFactory
			->createResponse()
			->withAddedHeader('foo', 'bar')
			->withBody($body)
		;

		$this::assertSame(
			'HTTP/1.1 200 OK'."\r\n".'foo: bar'."\r\n\r\n".'testbody',
			message_to_string($response)
		);
	}

	public function decompressDataProvider():array{
		return [
			'br'       => ['brotli_compress', 'br'],
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

		// https://github.com/kjdev/php-ext-brotli
		if($encoding === 'br' && (!extension_loaded('brotli') || !function_exists('brotli_compress'))){
			$this::markTestSkipped('N/A (ext-brotli not isntalled)');
		}

		$data     = str_repeat('compressed string ', 100);
		$expected = $data;
		$response = $this->responseFactory->createResponse();

		if($fn){
			$data     = $fn($data);
			$response = $response->withHeader('Content-Encoding', $encoding);
		}

		$response = $response->withBody($this->streamFactory->createStream($data));

		$this::assertSame($expected, decompress_content($response));
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

		decompress_content($response);
	}

	public function testUriIsAbsolute():void{
		$this::assertTrue(uriIsAbsolute($this->uriFactory->createUri('http://example.org')));
		$this::assertFalse(uriIsAbsolute($this->uriFactory->createUri('//example.org')));
		$this::assertFalse(uriIsAbsolute($this->uriFactory->createUri('/abs-path')));
		$this::assertFalse(uriIsAbsolute($this->uriFactory->createUri('rel-path')));
	}

	public function testUriIsNetworkPathReference():void{
		$this::assertFalse(uriIsNetworkPathReference($this->uriFactory->createUri('http://example.org')));
		$this::assertTrue(uriIsNetworkPathReference($this->uriFactory->createUri('//example.org')));
		$this::assertFalse(uriIsNetworkPathReference($this->uriFactory->createUri('/abs-path')));
		$this::assertFalse(uriIsNetworkPathReference($this->uriFactory->createUri('rel-path')));
	}

	public function testUriIsAbsolutePathReference():void{
		$this::assertFalse(uriIsAbsolutePathReference($this->uriFactory->createUri('http://example.org')));
		$this::assertFalse(uriIsAbsolutePathReference($this->uriFactory->createUri('//example.org')));
		$this::assertTrue(uriIsAbsolutePathReference($this->uriFactory->createUri('/abs-path')));
		$this::assertTrue(uriIsAbsolutePathReference($this->uriFactory->createUri('/')));
		$this::assertFalse(uriIsAbsolutePathReference($this->uriFactory->createUri('rel-path')));
	}

	public function testUriIsRelativePathReference():void{
		$this::assertFalse(uriIsRelativePathReference($this->uriFactory->createUri('http://example.org')));
		$this::assertFalse(uriIsRelativePathReference($this->uriFactory->createUri('//example.org')));
		$this::assertFalse(uriIsRelativePathReference($this->uriFactory->createUri('/abs-path')));
		$this::assertTrue(uriIsRelativePathReference($this->uriFactory->createUri('rel-path')));
		$this::assertTrue(uriIsRelativePathReference($this->uriFactory->createUri('')));
	}

	public function testUriAddAndRemoveQueryValues():void{
		$uri = $this->uriFactory->createUri();

		$uri = uriWithQueryValue($uri, 'a', 'b');
		$uri = uriWithQueryValue($uri, 'c', 'd');
		$uri = uriWithQueryValue($uri, 'e', null);
		$this::assertSame('a=b&c=d&e', $uri->getQuery());

		$uri = uriWithoutQueryValue($uri, 'c');
		$this::assertSame('a=b&e', $uri->getQuery());
		$uri = uriWithoutQueryValue($uri, 'e');
		$this::assertSame('a=b', $uri->getQuery());
		$uri = uriWithoutQueryValue($uri, 'a');
		$this::assertSame('', $uri->getQuery());
	}

	public function testUriWithQueryValueReplacesSameKeys():void{
		$uri = $this->uriFactory->createUri();

		$uri = uriWithQueryValue($uri, 'a', 'b');
		$uri = uriWithQueryValue($uri, 'c', 'd');
		$uri = uriWithQueryValue($uri, 'a', 'e');
		$this::assertSame('c=d&a=e', $uri->getQuery());
	}

	public function testUriWithoutQueryValueRemovesAllSameKeys():void{
		$uri = $this->uriFactory->createUri()->withQuery('a=b&c=d&a=e');

		$uri = uriWithoutQueryValue($uri, 'a');
		$this::assertSame('c=d', $uri->getQuery());
	}

	public function testUriRemoveNonExistingQueryValue():void{
		$uri = $this->uriFactory->createUri();
		$uri = uriWithQueryValue($uri, 'a', 'b');
		$uri = uriWithoutQueryValue($uri, 'c');
		$this::assertSame('a=b', $uri->getQuery());
	}

	public function testUriWithQueryValueHandlesEncoding():void{
		$uri = $this->uriFactory->createUri();
		$uri = uriWithQueryValue($uri, 'E=mc^2', 'ein&stein');
		$this::assertSame('E%3Dmc%5E2=ein%26stein', $uri->getQuery(), 'Decoded key/value get encoded');

		$uri = $this->uriFactory->createUri();
		$uri = uriWithQueryValue($uri, 'E%3Dmc%5e2', 'ein%26stein');
		$this::assertSame('E%3Dmc%5e2=ein%26stein', $uri->getQuery(), 'Encoded key/value do not get double-encoded');
	}

	public function testUriWithoutQueryValueHandlesEncoding():void{
		// It also tests that the case of the percent-encoding does not matter,
		// i.e. both lowercase "%3d" and uppercase "%5E" can be removed.
		$uri = $this->uriFactory->createUri()->withQuery('E%3dmc%5E2=einstein&foo=bar');
		$uri = uriWithoutQueryValue($uri, 'E=mc^2');
		$this::assertSame('foo=bar', $uri->getQuery(), 'Handles key in decoded form');

		$uri = $this->uriFactory->createUri()->withQuery('E%3dmc%5E2=einstein&foo=bar');
		$uri = uriWithoutQueryValue($uri, 'E%3Dmc%5e2');
		$this::assertSame('foo=bar', $uri->getQuery(), 'Handles key in encoded form');

		$uri = uriWithoutQueryValue(uriWithoutQueryValue($uri, 'foo'), ''); // coverage
		$this::assertSame('', $uri->getQuery());
	}

	public function testUriIsDefaultPort():void{

		foreach(URI_DEFAULT_PORTS as $scheme => $port){
			$uri = $this->uriFactory->createUri($scheme.'://localhost:'.$port);

			$this::assertTrue(uriIsDefaultPort($uri));
			$this->assertSame($scheme.'://localhost', (string)$uri);
		}

		$uri = $this->uriFactory->createUri('https://localhost:42');
		$this->assertSame('https://localhost:42', (string)$uri);

	}

	public function parseUrlProvider():array{
		return [
			['http://', null],
			['https://??????????A??.????', [
				'scheme' => 'https',
				'host'   => '??????????A??.????'
			]],
			['http://[2a00:f48:1008::212:183:10]:56?foo=bar', [
				'scheme' => 'http',
				'host'   => '[2a00:f48:1008::212:183:10]',
				'port'   => '56',
				'query'  => 'foo=bar',
			]]
		];
	}

	/**
	 * @dataProvider parseUrlProvider
	 */
	public function testParseUrl($url, $expected):void{
		$this::assertSame($expected, parseUrl($url));
	}

	public function testGetMimetype():void{
		$this::assertSame('application/json', getMimetypeFromExtension('json'));
		$this::assertSame('application/json', getMimetypeFromFilename('/path/to/some/file.json'));
		$this::assertNull(getMimetypeFromExtension('whatever'));
	}

}
