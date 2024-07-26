<?php
/**
 * SapiEmitterTest.php
 *
 * @created      14.02.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils\Emitter;

use chillerlan\HTTP\Utils\Emitter\ResponseEmitterInterface;
use chillerlan\HTTP\Utils\Emitter\SapiEmitter;
use chillerlan\HTTPTest\Utils\UtilTestAbstract;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Generator, RuntimeException;
use function array_column, array_map, array_pop, explode, header, implode, sprintf, strlen, trim;

final class SapiEmitterTest extends UtilTestAbstract{

	protected ResponseEmitterInterface $emitter;

	/**
	 * Returns an instance of the SapiEmitter that intercepts headers and output so that they can be examined
	 */
	protected function initEmitter(ResponseInterface $response, int $bufferSize = 8192):ResponseEmitterInterface{
		return new class ($response, $bufferSize) extends SapiEmitter{
			/** @var array<string, array{0: string, 1: bool, 2: int}> */
			protected array $headers = [];
			/** @var string[] */
			protected array $content = [];

			protected function sendHeader(string $header, bool $replace, int $response_code = 0):void{
				$this->headers[] = ['header' => $header, 'replace' => $replace, 'response_code' => $response_code];
			}

			protected function emitBuffer(string $buffer):void{
				$this->content[] = $buffer;
			}

			/** @return array<string, array{0: string, 1: bool, 2: int}> */
			public function getHeaders():array{
				return $this->headers;
			}

			/** @return string[] */
			public function getBody():array{
				return $this->content;
			}

			public function getBodyContent():string{
				return implode('', $this->content);
			}
		};
	}

	public function testSetHeaders():void{

		$response = $this->responseFactory
			->createResponse(204)
			->withHeader('x-foo', 'foo') // should be overwritten
			->withHeader('x-foo', 'bar')
			->withHeader('x-bar', 'foo')
			->withAddedHeader('set-cookie', 'cookie=one')
			->withAddedHeader('set-cookie', 'cookie2=two')
			->withAddedHeader('set-cookie', 'cookie=three')
		;

		$this->emitter = $this->initEmitter($response);
		$this->emitter->emit();

		$headers  = array_column($this->emitter->getHeaders(), 'header');
		$status   = array_pop($headers); // status line should be last
		$expected = [
			'X-Foo: bar',
			'X-Bar: foo',
			'Set-Cookie: cookie=three',
			'Set-Cookie: cookie2=two',
		];

		$this::assertCount(4, $headers);
		$this::assertSame($expected, $headers);
		$this::assertStringContainsString('HTTP/1.1 204', $status);
	}

	public function testEmit():void{
		$expected = 'Hello World!';

		$response = $this->responseFactory
			->createResponse(200)
			->withBody($this->streamFactory->createStream($expected))
		;

		$this->emitter = $this->initEmitter($response);
		$this->emitter->emit();

		$headers = $this->emitter->getHeaders();

		$this::assertSame($expected, $this->emitter->getBodyContent());
		$this::assertSame('Content-Length: '.strlen($expected), $headers[0]['header']);
	}

	public function testPreviousOutputException():void{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Output has been emitted previously; cannot emit response.');

		$response = $this->responseFactory
			->createResponse(200)
			->withBody($this->streamFactory->createStream('Hello World!'))
		;

		echo 'previous output';

		$this->emitter = $this->initEmitter($response);
		$this->emitter->emit();
	}

	public static function contentRangeProvider():Generator{
		$s = 'abcdefghijklmnopqrstuvwxyz';

		foreach([5, 20, 30, 100] as $b){
			foreach([
				[ 0,   0, $b, $s,  1, 'a'],
				[ 0,   1, $b, $s,  2, 'ab'],
				[ 0,   9, $b, $s, 10, 'abcdefghij'],
				[10,  19, $b, $s, 10, 'klmnopqrst'],
				[20,  25, $b, $s,  6, 'uvwxyz'],
				[ 0,  10, $b, $s, 11, 'abcdefghijk'],
				[10,  20, $b, $s, 11, 'klmnopqrstu'],
				[20,  30, $b, $s,  6, 'uvwxyz'],
				[ 0,  25, $b, $s, 26, $s],
				[ 0,  30, $b, $s, 26, $s],
				[ 0,  -1, $b, $s, 26, $s],
			] as $test){
				yield $test;
			}
		}
	}

	#[DataProvider('contentRangeProvider')]
	public function testEmitContentRange(
		int    $start,
		int    $end,
		int    $bufferSize,
		string $body,
		int    $expectedLength,
		string $expected,
	):void{

		$response = $this->responseFactory
			->createResponse(206)
			->withHeader('Content-Range', sprintf('bytes %s-%s/*', $start, $end))
			->withBody($this->streamFactory->createStream($body))
		;

		$this->emitter = $this->initEmitter($response, $bufferSize);
		$this->emitter->emit();

		$content = $this->emitter->getBodyContent();

		foreach($this->emitter->getHeaders() as $line){
			[$headerName, $headerContent] = array_map(trim(...), explode(':', $line['header'], 2));

			if($headerName === 'Content-Length'){
				$this::assertSame($expectedLength, (int)$headerContent);
			}
			elseif($headerName === 'Content-Range'){
				$this::assertSame(sprintf('bytes %s-%s/%s', $start, $end, strlen($body)), $headerContent);
			}
		}

		$this::assertSame($expectedLength, strlen($content));
		$this::assertSame($expected, $content);
	}

	public function testInvalidContentRangeRemovesHeader():void{

		$response = $this->responseFactory
			->createResponse(206)
			->withHeader('Content-Range', 'florps 0-1/*')
			->withBody($this->streamFactory->createStream('boo'))
		;

		$this::assertTrue($response->hasHeader('Content-Range'));
		$this::assertFalse($response->hasHeader('Content-Length'));

		$this->emitter = $this->initEmitter($response);
		$this->emitter->emit();

		$headers  = array_column($this->emitter->getHeaders(), 'header');

		$expected = [
			'Content-Length: 3',
			'HTTP/1.1 200 OK',
		];

		$this::assertSame($expected, $headers);
	}

	public function testEmitPartialContent():void{

		$response = $this->responseFactory
			->createResponse(200)
			->withHeader('Content-Length', '5')
			->withBody($this->streamFactory->createStream('Hello World!'))
		;

		$this->emitter = $this->initEmitter($response);
		$this->emitter->emit();

		$headers  = array_column($this->emitter->getHeaders(), 'header');

		$expected = [
			'Content-Length: 5',
			'HTTP/1.1 200 OK',
		];

		$this::assertSame($expected, $headers);
		$this::assertSame('Hello', $this->emitter->getBodyContent());
	}

}
