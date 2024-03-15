<?php
/**
 * Class URLExtractorClientFactory
 *
 * @created      15.03.2024
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2024 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils\Client\Factories;

use chillerlan\HTTP\Utils\Client\URLExtractor;
use chillerlan\PHPUnitHttp\HttpClientFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_MAXREDIRS;

/**
 *
 */
final class URLExtractorClientFactory implements HttpClientFactoryInterface{

	public function getClient(string $cacert, ResponseFactoryInterface $responseFactory):ClientInterface{

		$http = new Client([
			'verify'  => false,
			'headers' => [
				'User-Agent' => self::USER_AGENT,
			],
			'curl'    => [
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_MAXREDIRS      => 25,
			],
		]);

		// note: this client requires a request factory
		return new URLExtractor($http, new HttpFactory);
	}

}
