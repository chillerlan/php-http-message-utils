<?php
/**
 * Class EchoClientTest
 *
 * @created      15.03.2024
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2024 smiley
 * @license      MIT
 */

namespace chillerlan\HTTPTest\Utils\Client;

use chillerlan\HTTP\Utils\MessageUtil;
use chillerlan\HTTPTest\Utils\Client\Factories\EchoClientFactory;

/**
 *
 */
class EchoClientTest extends HTTPClientTestAbstract{

	protected string $HTTP_CLIENT_FACTORY = EchoClientFactory::class;

	public function testSendRequest():void{
		$url      = 'https://httpbin.org/get?whatever=value';
		$response = $this->httpClient->sendRequest($this->requestFactory->createRequest('GET', $url));
		$json     = MessageUtil::decodeJSON($response);

		$this::assertSame($url, $json->request->url);
		$this::assertSame('GET', $json->request->method);
		$this::assertSame('value', $json->request->params->{'whatever'});
		$this::assertSame('httpbin.org', $json->headers->{'Host'});
	}

	public function testNetworkError():void{
		$this::markTestSkipped('N/A');
	}

	public function testRequestError():void{
		$this::markTestSkipped('N/A');
	}

}
