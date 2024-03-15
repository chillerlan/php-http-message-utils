<?php
/**
 * Class URLExtractorTest
 *
 * @created      15.08.2019
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2019 smiley
 * @license      MIT
 */

declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils\Client;

use chillerlan\HTTPTest\Utils\Client\Factories\URLExtractorClientFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @property \chillerlan\HTTP\Utils\Client\URLExtractor $httpClient
 */
#[Group('slow')]
final class URLExtractorTest extends HTTPClientTestAbstract{

	protected string $HTTP_CLIENT_FACTORY = URLExtractorClientFactory::class;

	public function testSendRequest():void{

		try{
			// reminder: twitter does not delete shortened URLs of deleted tweets (this one was deleted in 2016)
			$this->httpClient->sendRequest($this->requestFactory->createRequest('GET', 'https://t.co/ZSS6nVOcVp'));

			$expected = [
				'https://bit.ly/1oesmr8',
				'http://tinyurl.com/jvc5y98',
				// interesting, this is a new one
				'https://redirect.viglink.com?u=https%3A%2F%2Fapi.guildwars2.com%2Fv2%2Fbuild&key=a7e37b5f6ff1de9cb410158b1013e54a&prodOvrd=RAC&opt=false',
				'https://api.guildwars2.com/v2/build',
				'',
			];

			$responses = $this->httpClient->getResponses();

			$this::assertCount(5, $responses);

			foreach($responses as $i => $r){
				$this::assertSame($expected[$i], $r->getHeaderLine('location'));
			}
		}
		catch(ExpectationFailedException){
			$this::markTestSkipped('extract error (host might have failed)');
		}

	}

	public function testExtract():void{

		try{
			$url = $this->httpClient->extract('https://t.co/ZSS6nVOcVp');

			$this::assertSame('https://api.guildwars2.com/v2/build', $url);
		}
		catch(ExpectationFailedException){
			$this::markTestSkipped('extract error (host might have failed)');
		}

	}

}
