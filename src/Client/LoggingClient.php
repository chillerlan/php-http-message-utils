<?php
/**
 * Class LoggingClient
 *
 * @created      07.08.2019
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2019 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTP\Utils\Client;

use chillerlan\HTTP\Utils\MessageUtil;
use Psr\Http\Client\{ClientExceptionInterface, ClientInterface};
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Psr\Log\{LoggerInterface, NullLogger};
use RuntimeException, Throwable;
use function sprintf;

/**
 * a silly logging wrapper (do not use in production!)
 *
 * @codeCoverageIgnore
 */
class LoggingClient implements ClientInterface{

	protected ClientInterface $http;
	protected LoggerInterface $logger;

	/**
	 * LoggingClient constructor.
	 */
	public function __construct(ClientInterface $http, LoggerInterface $logger = new NullLogger){
		$this->http   = $http;
		$this->logger = $logger;
	}

	public function setLogger(LoggerInterface $logger):static{
		$this->logger = $logger;

		return $this;
	}

	public function sendRequest(RequestInterface $request):ResponseInterface{

		try{
			$this->logger->debug(sprintf("\n----HTTP-REQUEST----\n%s", MessageUtil::toString($request)));

			$response = $this->http->sendRequest($request);

			$this->logger->debug(sprintf("\n----HTTP-RESPONSE---\n%s", MessageUtil::toString($response)));
		}
		catch(Throwable $e){
			$this->logger->error($e->getMessage());
			$this->logger->error($e->getTraceAsString());

			if(!$e instanceof ClientExceptionInterface){
				$msg = 'unexpected exception, does not implement "ClientExceptionInterface": "%s"';

				throw new RuntimeException(sprintf($msg, $e::class));
			}

			throw $e;
		}

		return $response;
	}

}
