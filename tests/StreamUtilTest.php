<?php
/**
 * Class StreamUtilTest
 *
 * @created      24.07.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\StreamUtil;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function fclose;
use function fopen;
use function stream_get_meta_data;
use function strlen;
use function substr;

/**
 *
 */
class StreamUtilTest extends TestCase{
	use FactoryTrait;

	public function testModeAllowsRead():void{
		$this::assertTrue(StreamUtil::modeAllowsRead('r+'));
		$this::assertFalse(StreamUtil::modeAllowsRead('w'));
	}

	public function testModeAllowsReadOnly():void{
		$this::assertTrue(StreamUtil::modeAllowsReadOnly('r'));
		$this::assertFalse(StreamUtil::modeAllowsReadOnly('r+b'));
	}

	public function testModeAllowsWrite():void{
		$this::assertTrue(StreamUtil::modeAllowsWrite('w+'));
		$this::assertFalse(StreamUtil::modeAllowsWrite('rb'));
	}

	public function testModeAllowsWriteOnly():void{
		$this::assertTrue(StreamUtil::modeAllowsWriteOnly('a'));
		$this::assertFalse(StreamUtil::modeAllowsWriteOnly('c+t'));
	}

	public function testModeAllowsReadWrite():void{
		$this::assertTrue(StreamUtil::modeAllowsReadWrite('r+e'));
		$this::assertFalse(StreamUtil::modeAllowsReadWrite('r'));
	}

	public function testCheckModeIsValidThrows():void{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('invalid fopen mode: b+');

		StreamUtil::validateMode('b+');
	}

	public function testModeAllowedFlagPositionIrrelevant():void{
		$mode = 'rwarrrrrw++++b12345';
		$this::assertTrue(StreamUtil::modeAllowsRead($mode));

		$fh = fopen(__DIR__.'/fopen-test.txt', $mode);
		$meta = stream_get_meta_data($fh);

		$this::assertSame(substr($mode, 0, 15), $meta['mode']);
		fclose($fh);
	}

	public function testGetContentsRewindsStream():void{
		$stream = $this->streamFactory->createStream();

		$stream->write('test');

		$this::assertSame(4, $stream->tell());
		$this::assertSame('test', StreamUtil::getContents($stream));
		$this::assertSame(0, $stream->tell());
	}

	public function testGetContentsFromUnreadableStream():void{
		$stream = $this->streamFactory->createStreamFromResource(fopen(__DIR__.'/fopen-test.txt', 'a'));

		$this::assertFalse($stream->isReadable());
		$this::assertNull(StreamUtil::getContents($stream));
	}

	public function testCopyToStream():void{
		$content = 'teststream';

		$streamA = $this->streamFactory->createStream($content);
		$streamB = $this->streamFactory->createStream();

		$bytesRead = StreamUtil::copyToStream($streamA, $streamB);

		$this::assertSame(strlen($content), $bytesRead);
		$this::assertSame($content, (string)$streamB); // -> "teststream"
	}

	public function testCopyToStreamWithMaxlength():void{
		$content   = 'teststream';
		$maxlength = 4;

		$streamA   = $this->streamFactory->createStream($content);
		$streamB   = $this->streamFactory->createStream();

		$bytesRead = StreamUtil::copyToStream($streamA, $streamB, $maxlength);

		$this::assertSame($maxlength, $bytesRead);
		$this::assertSame(substr($content, 0, $maxlength), (string)$streamB); // -> "test"
	}

	public function testCopyToStreamFromCurrentPosition():void{
		$content  = 'teststream';
		$position = 4;

		$streamA  = $this->streamFactory->createStream($content);
		$streamB  = $this->streamFactory->createStream();

		$streamA->seek($position);
		$pos = $streamA->tell();

		$bytesRead = StreamUtil::copyToStream($streamA, $streamB);

		$this::assertSame((strlen($content) - $pos), $bytesRead);
		$this::assertSame(substr($content, $position), (string)$streamB); // -> "stream"
	}

	public function testCopyToStreamException():void{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('$source must be readable and $destination must be writable');

		$streamA = $this->streamFactory->createStreamFromResource(fopen(__DIR__.'/fopen-test.txt', 'a'));
		$streamB = $this->streamFactory->createStream();

		StreamUtil::copyToStream($streamA, $streamB);
	}

}
