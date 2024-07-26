<?php
/**
 * Class SapiEmitter
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTP\Utils\Emitter;

use chillerlan\HTTP\Utils\HeaderUtil;
use RuntimeException;
use function header, headers_sent, is_array, ob_get_length, ob_get_level, sprintf;

class SapiEmitter extends ResponseEmitterAbstract{

	public function emit():void{

		if(ob_get_level() > 0 && ob_get_length() > 0){
			throw new RuntimeException('Output has been emitted previously; cannot emit response.');
		}

		if(headers_sent($file, $line)){
			throw new RuntimeException(sprintf('Headers already sent in file %s on line %s.', $file, $line));
		}

		$this->emitHeaders();
		$this->emitBody();
	}

	/**
	 * Emits the headers
	 *
	 *   There are two special-case header calls. The first is a header
	 *   that starts with the string "HTTP/" (case is not significant),
	 *   which will be used to figure out the HTTP status code to send.
	 *   For example, if you have configured Apache to use a PHP script
	 *   to handle requests for missing files (using the ErrorDocument
	 *   directive), you may want to make sure that your script generates
	 *   the proper status code.
	 *
	 *   The second special case is the "Location:" header.
	 *   Not only does it send this header back to the browser,
	 *   but it also returns a REDIRECT (302) status code to the browser
	 *   unless the 201 or a 3xx status code has already been set.
	 *
	 * @see https://www.php.net/manual/en/function.header.php
	 */
	protected function emitHeaders():void{
		$headers = HeaderUtil::normalize($this->response->getHeaders());

		foreach($headers as $name => $value){

			if($name === 'Set-Cookie'){
				continue;
			}

			$this->sendHeader(sprintf('%s: %s', $name, $value), true);
		}

		if(isset($headers['Set-Cookie']) && is_array($headers['Set-Cookie'])){

			foreach($headers['Set-Cookie'] as $cookie){
				$this->sendHeader(sprintf('Set-Cookie: %s', $cookie), false);
			}

		}

		// Set the status _after_ the headers, because of PHP's "helpful" behavior with location headers.
		// See https://github.com/slimphp/Slim/issues/1730
		$this->sendHeader($this->getStatusLine(), true, $this->response->getStatusCode());
	}

	/**
	 * Allow to intercept header calls in tests
	 *
	 * @codeCoverageIgnore (overridden in test)
	 */
	protected function sendHeader(string $header, bool $replace, int $response_code = 0):void{
		header($header, $replace, $response_code);
	}

}
