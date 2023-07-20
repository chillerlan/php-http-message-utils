<?php
/**
 * Class MimeTypeUtilTest
 *
 * @created      20.07.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\MimeTypeUtil;
use function file_get_contents;

/**
 *
 */
class MimeTypeUtilTest extends TestAbstract{

	public function testGetMimetypeFromExtension():void{
		$this::assertSame('application/json', MimeTypeUtil::getFromExtension('json'));
		$this::assertNull(MimeTypeUtil::getFromExtension('whatever'));
	}

	public function testGetMimeTypeFromFileName():void{
		$this::assertSame('application/json', MimeTypeUtil::getFromFilename('/path/to/some/file.json'));
	}

	public function testGetMimeTypeFromContent():void{
		$this::assertSame('application/x-empty', MimeTypeUtil::getFromContent('')); /// uh okay!?
		$this::assertSame('text/plain', MimeTypeUtil::getFromContent('foo'));
		$this::assertSame('text/x-php', MimeTypeUtil::getFromContent(file_get_contents(__FILE__)));
	}

}