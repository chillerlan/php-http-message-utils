<?php
/**
 * Class MimeTypeUtilTest
 *
 * @created      20.07.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */

declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\MimeTypeUtil;
use PHPUnit\Framework\TestCase;
use function file_get_contents;

/**
 *
 */
final class MimeTypeUtilTest extends TestCase{

	public function testGetMimetypeFromExtension():void{
		$this::assertSame('application/json', MimeTypeUtil::getFromExtension('json'));
		$this::assertNull(MimeTypeUtil::getFromExtension('whatever'));
	}

	public function testGetMimeTypeFromFileName():void{
		$this::assertNull(MimeTypeUtil::getFromFilename(''));
		$this::assertSame('application/json', MimeTypeUtil::getFromFilename('/path/to/some/file.json'));
		$this::assertSame('application/x-httpd-php', MimeTypeUtil::getFromFilename(__FILE__));
	}

	public function testGetMimeTypeFromContent():void{
		$this::assertSame('application/x-empty', MimeTypeUtil::getFromContent('')); /// uh okay!?
		$this::assertSame('text/plain', MimeTypeUtil::getFromContent('foo'));
		$this::assertSame('text/x-php', MimeTypeUtil::getFromContent(file_get_contents(__FILE__)));
	}

}
