<?php
/**
 * Class HeaderTest
 *
 * @created      28.03.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\HeaderUtil;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 *
 */
final class HeaderUtilTest extends UtilTestAbstract{

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
			'emptykvearray' => [[[]], []],
			'kvarraynumkey' => [[[69 => 420]], []],
			'arrayvalue'    => [['foo' => ['bar', 'baz']], ['Foo' => 'bar, baz']],
			'invalid: 2'    => [[2 => 2], []],
			'invalid: what' => [['what'], []],
			'empty value'   => [['empty-value' => ''], ['Empty-Value' => '']],
			'null value'    => [['null-value' => null], ['Null-Value' => '']],
			'space in name' => [['space name - header' => 'nope'], ['Spacename-Header' => 'nope']],
			'CRLF'          => [["CR\rLF-\nin-Na\r\n\r\nme" => " CR\rLF-\nin-va\r\n\r\nlue "], ['Crlf-In-Name' => 'CRLF-in-value']],
		];
	}

	#[DataProvider('headerDataProvider')]
	public function testNormalizeHeaders(array $headers, array $normalized):void{
		$this::assertSame($normalized, HeaderUtil::normalize($headers));
	}

	public function testNormalizeHeadersFromMessageInterface():void{

		$response = $this->responseFactory
			->createResponse()
			->withHeader('foo', 'bar')
			->withAddedHeader('what', 'nope')
			->withAddedHeader('what', 'why')
			->withHeader('set-cookie', 'foo=nope')
			->withAddedHeader('set-cookie', 'foo=bar')
			->withAddedHeader('set-cookie', 'what=why')
		;

		$expected = [
			'Foo'        => 'bar',
			'What'       => 'nope, why',
			'Set-Cookie' => [
				'foo'  => 'foo=bar',
				'what' => 'what=why',
			],
		];

		$this::assertSame($expected, HeaderUtil::normalize($response->getHeaders()));
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

		$expected = [
			'Accept'     => 'foo, bar',
			'X-Whatever' => 'nope',
			'X-Foo'      => 'bar, baz, what, nope',
		];

		$this::assertSame($expected, HeaderUtil::normalize($headers));

		$r = $this->responseFactory->createResponse();

		foreach(HeaderUtil::normalize($headers) as $k => $v){
			$r = $r->withAddedHeader($k, $v);
		}

		$expected = [
			'Accept'     => ['foo, bar'],
			'X-Whatever' => ['nope'],
			'X-Foo'      => ['bar, baz, what, nope'],
		];

		$this::assertSame($expected, $r->getHeaders());

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
			'CRLF-In-Name' => ["CR\rLF-\nin-Na\r\n\r\nme", 'Crlf-In-Name'],
		];
	}

	#[DataProvider('headerNameProvider')]
	public function testNormalizeHeaderName(string $name, string $expected):void{
		$this::assertSame($expected, HeaderUtil::normalizeHeaderName($name));
	}

	public static function headerValueProvider():array{
		return [
			'boolean'                => [true, '1'],
			'float'                  => [69.420, '69.42'],
			'int'                    => [69, '69'],
			'numeric string'         => ['69.420', '69.420'],
			'string with whitespace' => ['	 hello	 ', 'hello'],
			'CRLF-In-Value'          => [" CR\rLF-\nIn-Va\r\n\r\nlue ", 'CRLF-In-Value'],
		];
	}

	#[DataProvider('headerValueProvider')]
	public function testTrimValues(string|int|float|bool $value, string $expected):void{
		$this::assertSame([$expected], HeaderUtil::trimValues([$value]));
	}

}
