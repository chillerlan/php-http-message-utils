<?php
/**
 * Class EchoClientFactory
 *
 * @created      15.03.2024
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2024 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils\Client\Factories;

use chillerlan\HTTP\Utils\Client\EchoClient;
use chillerlan\PHPUnitHttp\HttpClientFactoryInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class EchoClientFactory implements HttpClientFactoryInterface{

	public function getClient(string $cacert, ResponseFactoryInterface $responseFactory):ClientInterface{
		return new EchoClient($responseFactory);
	}

}
