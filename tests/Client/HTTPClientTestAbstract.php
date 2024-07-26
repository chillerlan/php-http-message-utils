<?php
/**
 * Class HTTPClientTestAbstract
 *
 * @created      10.11.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils\Client;

use chillerlan\HTTP\Utils\MessageUtil;
use chillerlan\HTTPTest\Utils\UtilTestAbstract;
use chillerlan\PHPUnitHttp\HttpClientFactoryInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Throwable;

abstract class HTTPClientTestAbstract extends UtilTestAbstract{

	public function testSendRequest():void{

		try{
			$url      = 'https://httpbin.org/get';
			$response = $this->httpClient->sendRequest($this->requestFactory->createRequest('GET', $url));
			$json     = MessageUtil::decodeJSON($response);

			$this::assertSame($url, $json->url);
			$this::assertSame(HttpClientFactoryInterface::USER_AGENT, $json->headers->{'User-Agent'});
			$this::assertSame(200, $response->getStatusCode());
		}
		catch(Throwable $e){
			$this->markTestSkipped('error: '.$e->getMessage());
		}

	}

	public function testNetworkError():void{
		$this->expectException(ClientExceptionInterface::class);

		$this->httpClient->sendRequest($this->requestFactory->createRequest('GET', 'https://foo'));
	}

}
