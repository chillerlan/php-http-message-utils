<?php
/**
 * Class LoggingClientTest
 *
 * @created      10.08.2019
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2019 smiley
 * @license      MIT
 */

declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils\Client;

use chillerlan\HTTPTest\Utils\Client\Factories\LoggingClientFactory;
use PHPUnit\Framework\Attributes\Group;

/**
 * @property \chillerlan\HTTP\Utils\Client\LoggingClient $httpClient
 */
#[Group('slow')]
#[Group('output')]
final class LoggingClientTest extends HTTPClientTestAbstract{

	protected string $HTTP_CLIENT_FACTORY = LoggingClientFactory::class;

	public function testNetworkError():void{
		$this::markTestSkipped('N/A');
	}

	public function testRequestError():void{
		$this::markTestSkipped('N/A');
	}

}
