<?php
/**
 * Class UriUtilsTest
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\UriUtil;

/**
 *
 */
class UriUtilTest extends TestAbstract{

	public function testUriIsAbsolute():void{
		$this::assertTrue(UriUtil::isAbsolute($this->uriFactory->createUri('http://example.org')));
		$this::assertFalse(UriUtil::isAbsolute($this->uriFactory->createUri('//example.org')));
		$this::assertFalse(UriUtil::isAbsolute($this->uriFactory->createUri('/abs-path')));
		$this::assertFalse(UriUtil::isAbsolute($this->uriFactory->createUri('rel-path')));
	}

	public function testUriIsNetworkPathReference():void{
		$this::assertFalse(UriUtil::isNetworkPathReference($this->uriFactory->createUri('http://example.org')));
		$this::assertTrue(UriUtil::isNetworkPathReference($this->uriFactory->createUri('//example.org')));
		$this::assertFalse(UriUtil::isNetworkPathReference($this->uriFactory->createUri('/abs-path')));
		$this::assertFalse(UriUtil::isNetworkPathReference($this->uriFactory->createUri('rel-path')));
	}

	public function testUriIsAbsolutePathReference():void{
		$this::assertFalse(UriUtil::isAbsolutePathReference($this->uriFactory->createUri('http://example.org')));
		$this::assertFalse(UriUtil::isAbsolutePathReference($this->uriFactory->createUri('//example.org')));
		$this::assertTrue(UriUtil::isAbsolutePathReference($this->uriFactory->createUri('/abs-path')));
		$this::assertTrue(UriUtil::isAbsolutePathReference($this->uriFactory->createUri('/')));
		$this::assertFalse(UriUtil::isAbsolutePathReference($this->uriFactory->createUri('rel-path')));
	}

	public function testUriIsRelativePathReference():void{
		$this::assertFalse(UriUtil::isRelativePathReference($this->uriFactory->createUri('http://example.org')));
		$this::assertFalse(UriUtil::isRelativePathReference($this->uriFactory->createUri('//example.org')));
		$this::assertFalse(UriUtil::isRelativePathReference($this->uriFactory->createUri('/abs-path')));
		$this::assertTrue(UriUtil::isRelativePathReference($this->uriFactory->createUri('rel-path')));
		$this::assertTrue(UriUtil::isRelativePathReference($this->uriFactory->createUri('')));
	}

	public function testUriAddAndRemoveQueryValues():void{
		$uri = $this->uriFactory->createUri();

		$uri = UriUtil::withQueryValue($uri, 'a', 'b');
		$uri = UriUtil::withQueryValue($uri, 'c', 'd');
		$uri = UriUtil::withQueryValue($uri, 'e', null);
		$this::assertSame('a=b&c=d&e', $uri->getQuery());

		$uri = UriUtil::withoutQueryValue($uri, 'c');
		$this::assertSame('a=b&e', $uri->getQuery());
		$uri = UriUtil::withoutQueryValue($uri, 'e');
		$this::assertSame('a=b', $uri->getQuery());
		$uri = UriUtil::withoutQueryValue($uri, 'a');
		$this::assertSame('', $uri->getQuery());
	}

	public function testUriWithQueryValueReplacesSameKeys():void{
		$uri = $this->uriFactory->createUri();

		$uri = UriUtil::withQueryValue($uri, 'a', 'b');
		$uri = UriUtil::withQueryValue($uri, 'c', 'd');
		$uri = UriUtil::withQueryValue($uri, 'a', 'e');
		$this::assertSame('c=d&a=e', $uri->getQuery());
	}

	public function testUriWithoutQueryValueRemovesAllSameKeys():void{
		$uri = $this->uriFactory->createUri()->withQuery('a=b&c=d&a=e');

		$uri = UriUtil::withoutQueryValue($uri, 'a');
		$this::assertSame('c=d', $uri->getQuery());
	}

	public function testUriRemoveNonExistingQueryValue():void{
		$uri = $this->uriFactory->createUri();
		$uri = UriUtil::withQueryValue($uri, 'a', 'b');
		$uri = UriUtil::withoutQueryValue($uri, 'c');
		$this::assertSame('a=b', $uri->getQuery());
	}

	public function testUriWithQueryValueHandlesEncoding():void{
		$uri = $this->uriFactory->createUri();
		$uri = UriUtil::withQueryValue($uri, 'E=mc^2', 'ein&stein');
		$this::assertSame('E%3Dmc%5E2=ein%26stein', $uri->getQuery(), 'Decoded key/value get encoded');

		$uri = $this->uriFactory->createUri();
		$uri = UriUtil::withQueryValue($uri, 'E%3Dmc%5e2', 'ein%26stein');
		$this::assertSame('E%3Dmc%5e2=ein%26stein', $uri->getQuery(), 'Encoded key/value do not get double-encoded');
	}

	public function testUriWithoutQueryValueHandlesEncoding():void{
		// It also tests that the case of the percent-encoding does not matter,
		// i.e. both lowercase "%3d" and uppercase "%5E" can be removed.
		$uri = $this->uriFactory->createUri()->withQuery('E%3dmc%5E2=einstein&foo=bar');
		$uri = UriUtil::withoutQueryValue($uri, 'E=mc^2');
		$this::assertSame('foo=bar', $uri->getQuery(), 'Handles key in decoded form');

		$uri = $this->uriFactory->createUri()->withQuery('E%3dmc%5E2=einstein&foo=bar');
		$uri = UriUtil::withoutQueryValue($uri, 'E%3Dmc%5e2');
		$this::assertSame('foo=bar', $uri->getQuery(), 'Handles key in encoded form');

		$uri = UriUtil::withoutQueryValue(UriUtil::withoutQueryValue($uri, 'foo'), ''); // coverage
		$this::assertSame('', $uri->getQuery());
	}

	public function testUriIsDefaultPort():void{

		foreach(UriUtil::URI_DEFAULT_PORTS as $scheme => $port){
			$uri = $this->uriFactory->createUri($scheme.'://localhost:'.$port);

			$this::assertTrue(UriUtil::isDefaultPort($uri));
			$this->assertSame($scheme.'://localhost', (string)$uri);
		}

		$uri = $this->uriFactory->createUri('https://localhost:42');
		$this->assertSame('https://localhost:42', (string)$uri);

	}

}
