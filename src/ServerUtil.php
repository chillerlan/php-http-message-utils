<?php
/**
 * Class ServerUtil
 *
 * @created      29.03.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTP\Utils;

use Psr\Http\Message\{ServerRequestFactoryInterface, ServerRequestInterface, StreamFactoryInterface, StreamInterface,
	UploadedFileFactoryInterface, UploadedFileInterface, UriFactoryInterface, UriInterface};
use InvalidArgumentException;
use function array_keys, explode, function_exists, is_array, is_file, substr;

/**
 * @phpstan-type File     array{tmp_name: string, size: int, error: int, name: string, type: string}
 * @phpstan-type FileSpec array{tmp_name: string[], size: int[], error: int[], name: string[], type: string[]}
 */
final class ServerUtil{

	protected ServerRequestFactoryInterface $serverRequestFactory;
	protected UriFactoryInterface           $uriFactory;
	protected UploadedFileFactoryInterface  $uploadedFileFactory;
	protected StreamFactoryInterface        $streamFactory;

	public function __construct(
		ServerRequestFactoryInterface $serverRequestFactory,
		UriFactoryInterface           $uriFactory,
		UploadedFileFactoryInterface  $uploadedFileFactory,
		StreamFactoryInterface        $streamFactory,
	){
		$this->serverRequestFactory = $serverRequestFactory;
		$this->uriFactory           = $uriFactory;
		$this->uploadedFileFactory  = $uploadedFileFactory;
		$this->streamFactory        = $streamFactory;
	}

	/**
	 * Returns a ServerRequest populated with superglobals:
	 *  - $_GET
	 *  - $_POST
	 *  - $_COOKIE
	 *  - $_FILES
	 *  - $_SERVER
	 */
	public function createServerRequestFromGlobals():ServerRequestInterface{

		$serverRequest = $this->serverRequestFactory->createServerRequest(
			($_SERVER['REQUEST_METHOD'] ?? 'GET'),
			$this->createUriFromGlobals(),
			$_SERVER,
		);

		if(function_exists('getallheaders')){
			$allHeaders = getallheaders();

			if(is_array($allHeaders)){
				foreach($allHeaders as $name => $value){
					$serverRequest = $serverRequest->withHeader($name, $value);
				}
			}
		}

		$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? substr($_SERVER['SERVER_PROTOCOL'], 5) : '1.1';

		return $serverRequest
			->withProtocolVersion($protocol)
			->withCookieParams($_COOKIE)
			->withQueryParams($_GET)
			->withParsedBody($_POST)
			->withUploadedFiles($this->normalizeFiles($_FILES))
		;
	}

	/**
	 * Creates a UriInterface populated with values from $_SERVER.
	 */
	public function createUriFromGlobals():UriInterface{
		$hasPort  = false;
		$hasQuery = false;

		$uri = $this->uriFactory
			->createUri()
			->withScheme((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
		;

		if(isset($_SERVER['HTTP_HOST'])){
			$hostHeaderParts = explode(':', $_SERVER['HTTP_HOST']);
			$uri             = $uri->withHost($hostHeaderParts[0]);

			if(isset($hostHeaderParts[1])){
				$hasPort = true;
				$uri     = $uri->withPort((int)$hostHeaderParts[1]);
			}
		}
		elseif(isset($_SERVER['SERVER_NAME'])){
			$uri = $uri->withHost($_SERVER['SERVER_NAME']);
		}
		elseif(isset($_SERVER['SERVER_ADDR'])){
			$uri = $uri->withHost($_SERVER['SERVER_ADDR']);
		}

		if(!$hasPort && isset($_SERVER['SERVER_PORT'])){
			$uri = $uri->withPort($_SERVER['SERVER_PORT']);
		}

		if(isset($_SERVER['REQUEST_URI'])){
			$requestUriParts = explode('?', $_SERVER['REQUEST_URI']);
			$uri             = $uri->withPath($requestUriParts[0]);

			if(isset($requestUriParts[1])){
				$hasQuery = true;
				$uri      = $uri->withQuery($requestUriParts[1]);
			}
		}

		if(!$hasQuery && isset($_SERVER['QUERY_STRING'])){
			$uri = $uri->withQuery($_SERVER['QUERY_STRING']);
		}

		return $uri;
	}


	/**
	 * Returns an UploadedFile instance array.
	 *
	 * @param array<string, string> $files An array which respects $_FILES structure
	 *
	 * @return array<string, \Psr\Http\Message\UploadedFileInterface>
	 * @throws \InvalidArgumentException for unrecognized values
	 */
	public function normalizeFiles(iterable $files):array{
		$normalized = [];

		foreach($files as $key => $value){

			if($value instanceof UploadedFileInterface){
				$normalized[$key] = $value;
			}
			elseif(is_array($value) && isset($value['tmp_name'])){
				$normalized[$key] = $this->createUploadedFileFromSpec($value);
			}
			elseif(is_array($value)){
				// recursion
				$normalized[$key] = $this->normalizeFiles($value);
			}
			else{
				throw new InvalidArgumentException('Invalid value in files specification');
			}

		}

		return $normalized;
	}

	/**
	 * Creates an UploadedFile instance from a $_FILES specification.
	 *
	 * If the specification represents an array of values, this method will
	 * delegate to normalizeNestedFileSpec() and return that return value.
	 *
	 * @phpstan-param (File|FileSpec) $value
	 *
	 * @return \Psr\Http\Message\UploadedFileInterface|\Psr\Http\Message\UploadedFileInterface[]
	 */
	public function createUploadedFileFromSpec(array $value):UploadedFileInterface|array{

		if(is_array($value['tmp_name'])){
			return self::normalizeNestedFileSpec($value);
		}

		$stream = $this->createStreamFromFile($value['tmp_name']);

		return $this->uploadedFileFactory
			->createUploadedFile($stream, (int)$value['size'], (int)$value['error'], $value['name'], $value['type']);
	}

	/** @codeCoverageIgnore */
	private function createStreamFromFile(string $file):StreamInterface{

		if(is_file($file)){
			return $this->streamFactory->createStreamFromFile($file);
		}

		return $this->streamFactory->createStream($file);
	}

	/**
	 * Normalizes an array of file specifications.
	 *
	 * Loops through all nested files and returns a normalized array of
	 * UploadedFileInterface instances.
	 *
	 * @phpstan-param FileSpec $files
	 *
	 * @return \Psr\Http\Message\UploadedFileInterface[]
	 */
	public function normalizeNestedFileSpec(array $files):array{
		$normalized = [];

		foreach(array_keys($files['tmp_name']) as $key){
			$spec = [
				'tmp_name' => $files['tmp_name'][$key],
				'size'     => $files['size'][$key],
				'error'    => $files['error'][$key],
				'name'     => $files['name'][$key],
				'type'     => $files['type'][$key],
			];

			$normalized[$key] = self::createUploadedFileFromSpec($spec);
		}

		return $normalized;
	}

}
