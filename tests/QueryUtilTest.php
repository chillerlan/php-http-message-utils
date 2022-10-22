<?php
/**
 * Class QueryUtilTest
 *
 * @link https://github.com/guzzle/psr7/blob/c0dcda9f54d145bd4d062a6d15f54931a67732f9/tests/QueryTest.php
 *
 * @created      27.03.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\QueryUtil;
use PHPUnit\Framework\TestCase;
use const PHP_QUERY_RFC1738, PHP_QUERY_RFC3986;

/**
 *
 */
class QueryUtilTest extends TestCase{

	public function queryParamDataProvider():array{
		return [
			// don't remove empty values
			'BOOLEANS_AS_BOOL (no remove)' => [
				QueryUtil::BOOLEANS_AS_BOOL,
				false,
				['whatever' => null, 'nope' => '', 'true' => true, 'false' => false, 'array' => ['value' => false]],
			],
			// bool cast to types
			'BOOLEANS_AS_BOOL' => [
				QueryUtil::BOOLEANS_AS_BOOL,
				true,
				['true' => true, 'false' => false, 'array' => ['value' => false]],
			],
			'BOOLEANS_AS_INT' => [
				QueryUtil::BOOLEANS_AS_INT,
				true,
				['true' => 1, 'false' => 0, 'array' => ['value' => 0]],
			],
			'BOOLEANS_AS_INT_STRING' => [
				QueryUtil::BOOLEANS_AS_INT_STRING,
				true,
				['true' => '1', 'false' => '0', 'array' => ['value' => '0']],
			],
			'BOOLEANS_AS_STRING' => [
				QueryUtil::BOOLEANS_AS_STRING,
				true,
				['true' => 'true', 'false' => 'false', 'array' => ['value' => 'false']],
			],
		];
	}

	/**
	 * @dataProvider queryParamDataProvider
	 *
	 * @param array $expected
	 * @param int   $bool_cast
	 * @param bool  $remove_empty
	 */
	public function testCleanQueryParams(int $bool_cast, bool $remove_empty, array $expected):void{
		$data = ['whatever' => null, 'nope' => '   ', 'true' => true, 'false' => false, 'array' => ['value' => false]];

		$this::assertSame($expected, QueryUtil::cleanParams($data, $bool_cast, $remove_empty));
	}

	public function mergeQueryDataProvider():array{
		$uri    = 'http://localhost/whatever/';
		$params = ['foo' => 'bar'];

		return [
			'add nothing and clear the trailing question mark' => [$uri.'?', [], $uri],
			'add to URI without query'                         => [$uri, $params, $uri.'?foo=bar'],
			'overwrite existing param'                         => [$uri.'?foo=nope', $params, $uri.'?foo=bar'],
			'add to existing param'                            => [$uri.'?what=nope', $params, $uri.'?foo=bar&what=nope'],
		];
	}

	/**
	 * @dataProvider mergeQueryDataProvider
	 *
	 * @param string $uri
	 * @param array  $params
	 * @param string $expected
	 */
	public function testMergeQuery(string $uri, array $params, string $expected):void{
		$merged = QueryUtil::merge($uri, $params);
		$this::assertSame($expected, $merged);
	}

	public function testBuildQuery():void{
		$data = ['foo' => 'bar', 'whatever?' => 'nope!'];

		$this::assertSame('foo=bar&whatever%3F=nope%21', QueryUtil::build($data));
		$this::assertSame('foo=bar&whatever?=nope!', QueryUtil::build($data, QueryUtil::NO_ENCODING));
		$this::assertSame('foo=bar, whatever?=nope!', QueryUtil::build($data, QueryUtil::NO_ENCODING, ', '));
		$this::assertSame('foo="bar", whatever?="nope!"', QueryUtil::build($data, QueryUtil::NO_ENCODING, ', ', '"'));

		$data['florps'] = ['nope', 'nope', 'nah'];
		$this::assertSame(
			'florps="nah", florps="nope", florps="nope", foo="bar", whatever?="nope!"',
			QueryUtil::build($data, QueryUtil::NO_ENCODING, ', ', '"')
		);
	}

	public function testBuildQuerySort():void{
		$this::assertSame('a=2&b=1&b=2&b=3&c=1&d=4', QueryUtil::build(['c' => 1, 'a' => 2, 'b' => [3, 1, 2], 'd' => 4]));
	}

	public function parseQueryProvider():array{
		return [
			'Does not need to parse when the string is empty'            => ['', []],
			'Can parse mult-values items'                                => ['q=a&q=b', ['q' => ['a', 'b']]],
			'Can parse multi-valued items that use numeric indices'      => ['q[0]=a&q[1]=b', ['q[0]' => 'a', 'q[1]' => 'b']],
			'Can parse duplicates and does not include numeric indices'  => ['q[]=a&q[]=b', ['q[]' => ['a', 'b']]],
			'Ensures that the value of "q" is an array'                  => ['q[]=a', ['q[]' => 'a']],
			'Does not modify "." to "_" like parse_str()'                => ['q.a=a&q.b=b', ['q.a' => 'a', 'q.b' => 'b']],
			'Can decode %20 to " "'                                      => ['q%20a=a%20b', ['q a' => 'a b']],
			'Can parse strings with no values by assigning each to null' => ['a&q', ['a' => null, 'q' => null]],
			'Does not strip trailing equal signs'                        => ['data=abc=', ['data' => 'abc=']],
			'Can store duplicates without affecting other values'        => ['foo=a&foo=b&µ=c', ['foo' => ['a', 'b'], 'µ' => 'c']],
			'Sets value to null when no "=" is present'                  => ['foo', ['foo' => null]],
			'Preserves "0" keys'                                         => ['0', ['0' => null]],
			'Sets the value to an empty string when "=" is present'      => ['0=', ['0' => '']],
			'Preserves falsey keys 1'                                    => ['var=0', ['var' => '0']],
			'Preserves falsey keys 2'                                    => ['a[b][c]=1&a[b][c]=2', ['a[b][c]' => ['1', '2']]],
			'Preserves falsey keys 3'                                    => ['a[b]=c&a[d]=e', ['a[b]' => 'c', 'a[d]' => 'e']],
			'Can parse multi-values items'                               => ['q=a&q=b&q=c', ['q' => ['a', 'b', 'c']]],
		];
	}

	/**
	 * @dataProvider parseQueryProvider
	 */
	public function testParsesQueries(string $input, array $output):void{
		$this::assertSame($output, QueryUtil::parse($input));
	}

	/**
	 * @dataProvider parseQueryProvider
	 */
	public function testParsesAndBuildsQueries(string $input): void{
		$result = QueryUtil::parse($input, QueryUtil::NO_ENCODING);

		$this::assertSame($input, QueryUtil::build($result, QueryUtil::NO_ENCODING));
	}

	public function testDoesNotDecode():void{
		$this::assertSame(['foo%20' => 'bar'], QueryUtil::parse('foo%20=bar', QueryUtil::NO_ENCODING));
	}

	public function testEncodesWithRfc1738():void{
		$this::assertSame('foo+bar=baz%2B', QueryUtil::build(['foo bar' => 'baz+'], PHP_QUERY_RFC1738));
	}

	public function testEncodesWithRfc3986():void{
		$this::assertSame('foo%20bar=baz%2B', QueryUtil::build(['foo bar' => 'baz+'], PHP_QUERY_RFC3986));
	}

	public function testDoesNotEncode():void{
		$this::assertSame('foo bar=baz+', QueryUtil::build(['foo bar' => 'baz+'], QueryUtil::NO_ENCODING));
	}

	public function testCanControlDecodingType():void{
		$this::assertSame('foo+bar', QueryUtil::parse('var=foo+bar', PHP_QUERY_RFC3986)['var']);
		$this::assertSame('foo bar', QueryUtil::parse('var=foo+bar', PHP_QUERY_RFC1738)['var']);
	}

	public function testBuildBooleans():void{
		$this::assertSame('false=0&true=1', QueryUtil::build(['true' => true, 'false' => false]));

		$this::assertSame(
			'bar=0&bar=false&foo=1&foo=true',
			QueryUtil::build(['foo' => [true, 'true'], 'bar' => [false, 'false']], PHP_QUERY_RFC1738)
		);
	}

	public function testParseDoesTrimQuestionMark():void{
		$this::assertSame(QueryUtil::parse('?q=a'), ['q' => 'a']);
	}

	public function parseUrlProvider():array{
		return [
			['http://', null],
			['https://яндекAс.рф', [
				'scheme' => 'https',
				'host'   => 'яндекAс.рф'
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
		$this::assertSame($expected, QueryUtil::parseUrl($url));
	}

}
