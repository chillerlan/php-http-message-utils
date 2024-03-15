<?php
/**
 * Class EchoClient
 *
 * @created      15.03.2024
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2024 smiley
 * @license      MIT
 */

namespace chillerlan\HTTP\Utils\Client;

use chillerlan\HTTP\Utils\MessageUtil;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestInterface, ResponseFactoryInterface, ResponseInterface};
use Throwable;
use function json_encode, strlen;

/**
 * Echoes the http request back (as a JSON object)
 */
class EchoClient implements ClientInterface{

	/**
	 * EchoClient constructor.
	 */
	public function __construct(
		protected ResponseFactoryInterface $responseFactory
	){

	}

	public function sendRequest(RequestInterface $request):ResponseInterface{
		$response = $this->responseFactory->createResponse();

		try{
			$content = MessageUtil::toJSON($request);
		}
		catch(Throwable $e){
			$response = $response->withStatus(500);
			$content  = json_encode(['error' => $e->getMessage()]);
		}

		$response = $response
			->withHeader('Content-Type', 'application/json')
			->withHeader('Content-Length', (string)strlen($content))
		;

		$response->getBody()->write($content);

		return $response;
	}

}
