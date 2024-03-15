<?php
/**
 * Class ChillerlanHttpClientFactory
 *
 * @created      14.03.2024
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2024 smiley
 * @license      MIT
 */

declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils\Client\Factories;

use chillerlan\HTTP\Utils\Client\LoggingClient;
use chillerlan\PHPUnitHttp\{GuzzleHttpClientFactory, HttpClientFactoryInterface};
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\AbstractLogger;
use Stringable;
use function date;
use function printf;

final class LoggingClientFactory implements HttpClientFactoryInterface{

	public function getClient(string $cacert, ResponseFactoryInterface $responseFactory):ClientInterface{
		$http   = (new GuzzleHttpClientFactory)->getClient($cacert, $responseFactory);
		$logger = new class () extends AbstractLogger{
			public function log($level, string|Stringable $message, array $context = []):void{
				printf("\n[%s][%s] LoggingClientTest: %s", date('Y-m-d H:i:s'), $level, $message);
			}
		};

		return new LoggingClient($http, $logger);
	}

}
