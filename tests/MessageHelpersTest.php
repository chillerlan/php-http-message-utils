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

use TypeError;
use function chillerlan\HTTP\Utils\{getMimetypeFromExtension, getMimetypeFromFilename, r_rawurlencode};

class MessageHelpersTest extends TestAbstract{

	public static function rawurlencodeDataProvider():array{
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

	public function testGetMimetype():void{
		$this::assertSame('application/json', getMimetypeFromExtension('json'));
		$this::assertSame('application/json', getMimetypeFromFilename('/path/to/some/file.json'));
		$this::assertNull(getMimetypeFromExtension('whatever'));
	}

}
