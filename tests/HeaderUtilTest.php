<?php
/**
 * Class HeaderTest
 *
 * @created      28.03.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\HeaderUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class HeaderUtilTest extends TestCase{
	use FactoryTrait;

	public static function headerDataProvider():array{
		return [
			'content-Type'  => [['Content-Type' => 'application/x-www-form-urlencoded'], ['Content-Type' => 'application/x-www-form-urlencoded']],
			'lowercasekey'  => [['lowercasekey' => 'lowercasevalue'], ['Lowercasekey' => 'lowercasevalue']],
			'UPPERCASEKEY'  => [['UPPERCASEKEY' => 'UPPERCASEVALUE'], ['Uppercasekey' => 'UPPERCASEVALUE']],
			'mIxEdCaSeKey'  => [['mIxEdCaSeKey' => 'MiXeDcAsEvAlUe'], ['Mixedcasekey' => 'MiXeDcAsEvAlUe']],
			'31i71casekey'  => [['31i71casekey' => '31i71casevalue'], ['31i71casekey' => '31i71casevalue']],
			'numericvalue'  => [['numericvalue:1'], ['Numericvalue' => '1']],
			'numericvalue2' => [['numericvalue' => 2], ['Numericvalue' => '2']],
			'keyvaluearray' => [[['foo' => 'bar']], ['Foo' => 'bar']],
			'arrayvalue'    => [['foo' => ['bar', 'baz']], ['Foo' => 'bar, baz']],
			'invalid: 2'    => [[2 => 2], []],
			'invalid: what' => [['what'], []],
		];
	}

	#[DataProvider('headerDataProvider')]
	public function testNormalizeHeaders(array $headers, array $normalized):void{
		$this::assertSame($normalized, HeaderUtil::normalize($headers));
	}

	public function testCombineHeaderFields():void{

		$headers = [
			'accept:',
			'Accept: foo',
			'accept' => 'bar',
			'x-Whatever :nope',
			'X-whatever' => '',
			'x-foo' => 'bar',
			'x - fOO: baz ',
			' x-foo ' => ['what', 'nope'],
		];

		$this::assertSame([
			'Accept'     => 'foo, bar',
			'X-Whatever' => 'nope',
			'X-Foo'      => 'bar, baz, what, nope',
		], HeaderUtil::normalize($headers));

		$r = $this->responseFactory->createResponse();

		foreach(HeaderUtil::normalize($headers) as $k => $v){
			$r = $r->withAddedHeader($k, $v);
		}

		$this::assertSame([
			'Accept'     => ['foo, bar'],
			'X-Whatever' => ['nope'],
			'X-Foo'      => ['bar, baz, what, nope'],
		], $r->getHeaders());

	}

	public function testCombinedCookieHeaders():void{

		$headers = [
			'Set-Cookie: foo=bar',
			'Set-Cookie: foo=baz',
			'Set-Cookie: whatever=nope; HttpOnly',
		];

		$this::assertSame([
			'Set-Cookie' => [
				'foo'      => 'foo=baz',
				'whatever' => 'whatever=nope; HttpOnly',
			],
		], HeaderUtil::normalize($headers));
	}

	public static function headerNameProvider():array{
		return [
			'content-Type' => ['content-Type', 'Content-Type'],
			'x-spaCE -keY' => ['x-spaCE -keY', 'X-Space-Key' ],
			'lowercasekey' => ['lowercasekey', 'Lowercasekey'],
			'UPPERCASEKEY' => ['UPPERCASEKEY', 'Uppercasekey'],
			'mIxEdCaSeKey' => ['mIxEdCaSeKey', 'Mixedcasekey'],
			'31i71casekey' => ['31i71casekey', '31i71casekey'],
		];
	}

	#[DataProvider('headerNameProvider')]
	public function testNormalizeHeaderName(string $name, string $expected):void{
		$this::assertSame($expected, HeaderUtil::normalizeHeaderName($name));
	}

}
