<?php
/**
 * Class ServerUtilTest
 *
 * @created      31.01.2019
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2019 smiley
 * @license      MIT
 *
 * @noinspection PhpArrayWriteIsNotUsedInspection
 */

namespace chillerlan\HTTPTest\Utils;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function microtime;
use function time;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

class ServerUtilTest extends TestCase{
	use FactoryTrait;

	public static function dataGetUriFromGlobals():array{

		$server = [
			'REQUEST_URI'        => '/blog/article.php?id=10&user=foo',
			'SERVER_PORT'        => '443',
			'SERVER_ADDR'        => '217.112.82.20',
			'SERVER_NAME'        => 'www.example.org',
			'SERVER_PROTOCOL'    => 'HTTP/1.1',
			'REQUEST_METHOD'     => 'POST',
			'QUERY_STRING'       => 'id=10&user=foo',
			'DOCUMENT_ROOT'      => '/path/to/your/server/root/',
			'HTTP_HOST'          => 'www.example.org',
			'HTTPS'              => 'on',
			'REMOTE_ADDR'        => '193.60.168.69',
			'REMOTE_PORT'        => '5390',
			'SCRIPT_NAME'        => '/blog/article.php',
			'SCRIPT_FILENAME'    => '/path/to/your/server/root/blog/article.php',
			'PHP_SELF'           => '/blog/article.php',
			'REQUEST_TIME'       => time(), // phpunit fix
			'REQUEST_TIME_FLOAT' => microtime(true), // phpunit fix
		];

		return [
			'HTTPS request' => [
				'https://www.example.org/blog/article.php?id=10&user=foo',
				$server,
			],
			'HTTPS request with different on value' => [
				'https://www.example.org/blog/article.php?id=10&user=foo',
				array_merge($server, ['HTTPS' => '1']),
			],
			'HTTP request' => [
				'http://www.example.org/blog/article.php?id=10&user=foo',
				array_merge($server, ['HTTPS' => 'off', 'SERVER_PORT' => '80']),
			],
			'HTTP_HOST missing -> fallback to SERVER_NAME' => [
				'https://www.example.org/blog/article.php?id=10&user=foo',
				array_merge($server, ['HTTP_HOST' => null]),
			],
			'HTTP_HOST and SERVER_NAME missing -> fallback to SERVER_ADDR' => [
				'https://217.112.82.20/blog/article.php?id=10&user=foo',
				array_merge($server, ['HTTP_HOST' => null, 'SERVER_NAME' => null]),
			],
			'No query String' => [
				'https://www.example.org/blog/article.php',
				array_merge($server, ['REQUEST_URI' => '/blog/article.php', 'QUERY_STRING' => '']),
			],
			'Host header with port' => [
				'https://www.example.org:8324/blog/article.php?id=10&user=foo',
				array_merge($server, ['HTTP_HOST' => 'www.example.org:8324']),
			],
			'Different port with SERVER_PORT' => [
				'https://www.example.org:8324/blog/article.php?id=10&user=foo',
				array_merge($server, ['SERVER_PORT' => '8324']),
			],
			'REQUEST_URI missing query string' => [
				'https://www.example.org/blog/article.php?id=10&user=foo',
				array_merge($server, ['REQUEST_URI' => '/blog/article.php']),
			],
			'Empty server variable' => [
				'http://localhost',
				['REQUEST_TIME' => time(), 'SCRIPT_NAME' => '/blog/article.php'], // phpunit fix
			],
		];
	}

	#[DataProvider('dataGetUriFromGlobals')]
	public function testCreateUriFromGlobals(string $expected, array $serverParams){
		$_SERVER = $serverParams;

		$this::assertSame($expected, (string)$this->server->createUriFromGlobals());
	}

	public function testCreateServerRequestFromGlobals():void{

		$_SERVER = [
			'REQUEST_URI'        => '/blog/article.php?id=10&user=foo',
			'SERVER_PORT'        => '443',
			'SERVER_ADDR'        => '217.112.82.20',
			'SERVER_NAME'        => 'www.example.org',
			'SERVER_PROTOCOL'    => 'HTTP/1.1',
			'REQUEST_METHOD'     => 'POST',
			'QUERY_STRING'       => 'id=10&user=foo',
			'DOCUMENT_ROOT'      => '/path/to/your/server/root/',
			'HTTP_HOST'          => 'www.example.org',
			'HTTPS'              => 'on',
			'REMOTE_ADDR'        => '193.60.168.69',
			'REMOTE_PORT'        => '5390',
			'SCRIPT_NAME'        => '/blog/article.php',
			'SCRIPT_FILENAME'    => '/path/to/your/server/root/blog/article.php',
			'PHP_SELF'           => '/blog/article.php',
			'REQUEST_TIME'       => time(), // phpunit fix
			'REQUEST_TIME_FLOAT' => microtime(true), // phpunit fix
		];

		$_COOKIE = [
			'logged-in' => 'yes!'
		];

		$_POST = [
			'name'  => 'Pesho',
			'email' => 'pesho@example.com',
		];

		$_GET = [
			'id' => 10,
			'user' => 'foo',
		];

		$_FILES = [
			'file' => [
				'name'     => 'MyFile.txt',
				'type'     => 'text/plain',
				'tmp_name' => __DIR__.'/uploaded_file.tmp',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 123,
			]
		];

		$server = $this->server->createServerRequestFromGlobals();

		$this::assertSame('POST', $server->getMethod());
		$this::assertSame(['Host' => ['www.example.org']], $server->getHeaders());
		$this::assertSame('', (string) $server->getBody());
		$this::assertSame('1.1', $server->getProtocolVersion());
		$this::assertSame($_COOKIE, $server->getCookieParams());
		$this::assertSame($_POST, $server->getParsedBody());
		$this::assertSame($_GET, $server->getQueryParams());

		$this::assertEquals(
			$this->uriFactory->createUri('https://www.example.org/blog/article.php?id=10&user=foo'),
			$server->getUri()
		);

		$file = $server->getUploadedFiles()['file'];

		$this::assertSame('MyFile.txt', $file->getClientFilename());
		$this::assertSame('text/plain', $file->getClientMediaType());
		$this::assertSame(123, $file->getSize());
		$this::assertSame(UPLOAD_ERR_OK, $file->getError());
		$this::assertStringContainsString('uploaded file content', (string)$file->getStream());
	}

	public function testNormalizeMultipleFiles():void{

		$tmp = __DIR__.'/uploaded_file.tmp';

		$files = [
			'files' => [
				'name'     => ['MyFile1.txt', 'MyFile2.gif', 'MyFile3.txt'],
				'type'     => ['text/plain','image/gif','text/plain',],
				'tmp_name' => [$tmp, $tmp, $tmp],
				'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_OK],
				'size'     => [123, 456, 789],
			]
		];

		/** @var array $normalized */
		$normalized = $this->server->normalizeFiles($files)['files'];

		$this::assertCount(3, $normalized);
		$this::assertSame('MyFile1.txt', $normalized[0]->getClientFilename());
		$this::assertSame('MyFile2.gif', $normalized[1]->getClientFilename());
		$this::assertSame('MyFile3.txt', $normalized[2]->getClientFilename());

		$this::assertSame(UPLOAD_ERR_PARTIAL, $normalized[1]->getError());

		$files = [
			'files' => [
				$this->uploadedFileFactory->createUploadedFile(
					$this->streamFactory->createStreamFromFile($tmp),
					123,
					UPLOAD_ERR_OK,
					'MyFile1.txt',
					'text/plain'
				),
				[
					'name'     => 'MyFile2.gif',
					'type'     => 'image/gif',
					'tmp_name' => $tmp,
					'error'    => UPLOAD_ERR_PARTIAL,
					'size'     => 456,
				],
				[
					'name'     => 'MyFile3.txt',
					'type'     => 'text/plain',
					'tmp_name' => $tmp,
					'error'    => UPLOAD_ERR_OK,
					'size'     => 789,
				],
			]
		];

		/** @var array $normalized */
		$normalized = $this->server->normalizeFiles($files)['files'];

		$this::assertCount(3, $normalized);
		$this::assertSame('MyFile1.txt', $normalized[0]->getClientFilename());
		$this::assertSame('MyFile2.gif', $normalized[1]->getClientFilename());
		$this::assertSame('MyFile3.txt', $normalized[2]->getClientFilename());

		$this::assertSame(UPLOAD_ERR_PARTIAL, $normalized[1]->getError());
	}

	public function testNormalizeFilesInvalidValueException():void{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid value in files specification');
		$this->server->normalizeFiles(['files' => 'foo']);
	}

}
