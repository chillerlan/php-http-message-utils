<?php
/**
 * Class ResponseEmitterAbstract
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */

declare(strict_types=1);

namespace chillerlan\HTTP\Utils\Emitter;

use Psr\Http\Message\{ResponseInterface, StreamInterface};
use InvalidArgumentException, RuntimeException;
use function connection_status, flush, in_array, intval, preg_match, sprintf, strlen, strtolower, trim;
use const CONNECTION_NORMAL;

/**
 * @see https://datatracker.ietf.org/doc/html/rfc2616
 * @see https://datatracker.ietf.org/doc/html/rfc9110
 */
abstract class ResponseEmitterAbstract implements ResponseEmitterInterface{

	protected StreamInterface $body;
	protected bool            $hasCustomLength;
	protected bool            $hasContentRange;
	protected int             $rangeStart  = 0;
	protected int             $rangeLength = 0;

	/**
	 * ResponseEmitter constructor
	 */
	public function __construct(
		protected ResponseInterface $response,
		protected int               $bufferSize = 65536
	){

		if($this->bufferSize < 1){
			throw new InvalidArgumentException('Buffer length must be greater than zero.'); // @codeCoverageIgnore
		}

		$this->body            = $this->response->getBody();
		$this->hasContentRange = $this->response->getStatusCode() === 206 && $this->response->hasHeader('Content-Range');

		$this->setContentLengthHeader();

		if($this->body->isSeekable()){
			$this->body->rewind();
		}

	}

	/**
	 * Checks whether the response has (or is supposed to have) a body
	 *
	 * @see https://datatracker.ietf.org/doc/html/rfc9110#name-informational-1xx
	 * @see https://datatracker.ietf.org/doc/html/rfc9110#name-204-no-content
	 * @see https://datatracker.ietf.org/doc/html/rfc9110#name-205-reset-content
	 * @see https://datatracker.ietf.org/doc/html/rfc9110#name-304-not-modified
	 */
	protected function hasBody():bool{
		$status = $this->response->getStatusCode();
		// these response codes never return a body
		if($status < 200 || in_array($status, [204, 205, 304])){
			return false;
		}

		return $this->body->isReadable() && $this->body->getSize() > 0;
	}

	/**
	 * Returns a full status line for the given response, e.g. "HTTP/1.1 200 OK"
	 */
	protected function getStatusLine():string{

		$status = sprintf(
			'HTTP/%s %d %s',
			$this->response->getProtocolVersion(),
			$this->response->getStatusCode(),
			$this->response->getReasonPhrase(),
		);

		// the reason phrase may be empty, so we make sure there's no extra trailing spaces in the status line
		return trim($status);
	}

	/**
	 * Sets/adjusts the Content-Length header
	 *
	 * (technically we could do this in a PSR-15 middleware but this class is supposed to work as standalone as well)
	 *
	 * @see https://datatracker.ietf.org/doc/html/rfc9110#name-content-length
	 */
	protected function setContentLengthHeader():void{
		$this->hasCustomLength = false;

		// remove the content-length header if body is not present
		if(!$this->hasBody()){
			$this->response = $this->response->withoutHeader('Content-Length');

			return;
		}

		// response has a content-range header set
		if($this->hasContentRange){
			$parsed = $this->parseContentRange();

			if($parsed === null){
				$this->hasContentRange = false;

				// content range is invalid, we'll remove the header send the full response with a code 200 instead
				// @see https://datatracker.ietf.org/doc/html/rfc9110#status.416 (note)
				$this->response = $this->response
					->withStatus(200, 'OK')
					->withoutHeader('Content-Range')
					->withHeader('Content-Length', (string)$this->body->getSize())
				;

				return;
			}

			[$this->rangeStart, $end, $total, $this->rangeLength] = $parsed;

			$this->response = $this->response
				// adjust the content-range header to include the full response size
				->withHeader('Content-Range', sprintf('bytes %s-%s/%s', $this->rangeStart, $end, $total))
				// add the content-length header with the partially fulfilled size
				->withHeader('Content-Length', (string)$this->rangeLength)
			;

			return;
		}

		// add the header if it's missing
		if(!$this->response->hasHeader('Content-Length')){
			$this->response = $this->response->withHeader('Content-Length', (string)$this->body->getSize());

			return;
		}

		// a header was present
		$contentLength = (int)$this->response->getHeaderLine('Content-Length');
		// we don't touch the custom value that has been set for whatever reason
		if($contentLength < $this->body->getSize()){
			$this->hasCustomLength = true;
			$this->rangeLength     = $contentLength;
		}

	}

	/**
	 * @see https://datatracker.ietf.org/doc/html/rfc9110#name-content-range
	 */
	protected function parseContentRange():array|null{
		$contentRange = $this->response->getHeaderLine('Content-Range');
		if(preg_match('/(?P<unit>[a-z]+)\s+(?P<start>\d+)-(?P<end>\d+)\/(?P<total>\d+|\*)/i', $contentRange, $matches)){
			// we only accept the "bytes" unit here
			if(strtolower($matches['unit']) !== 'bytes'){
				return null;
			}

			$start  = intval($matches['start']);
			$end    = intval($matches['end']);
			$total  = ($matches['total'] === '*') ? $this->body->getSize() : intval($matches['total']);
			$length = ($end - $start + 1);

			if($end < $start){
				return null;
			}

			// we're being generous and adjust if the end is greater than the total size
			if($end > $total){
				$length = ($total - $start);
			}

			return [$start, $end, $total, $length];
		}

		return null;
	}

	/**
	 * emits the given buffer
	 *
	 * @codeCoverageIgnore (overridden in test)
	 */
	protected function emitBuffer(string $buffer):void{
		echo $buffer;
	}

	/**
	 * emits the body of the given response with respect to the parameters given in content-range and content-length headers
	 */
	protected function emitBody():void{

		if(!$this->hasBody()){
			return;
		}

		// a length smaller than the total body size was specified
		if($this->hasCustomLength === true){
			$this->emitBodyRange(0, $this->rangeLength);

			return;
		}

		// a content-range header was set
		if($this->hasContentRange === true){
			$this->emitBodyRange($this->rangeStart, $this->rangeLength);

			return;
		}

		// dump the whole body
		while(!$this->body->eof()){
			$this->emitBuffer($this->body->read($this->bufferSize));

			if(connection_status() !== CONNECTION_NORMAL){
				break; // @codeCoverageIgnore
			}
		}

	}

	/**
	 * emits a part of the body
	 */
	protected function emitBodyRange(int $start, int $length):void{
		flush();

		if(!$this->body->isSeekable()){
			throw new RuntimeException('body must be seekable'); // @codeCoverageIgnore
		}

		$this->body->seek($start);

		while($length >= $this->bufferSize && !$this->body->eof()){
			$contents  = $this->body->read($this->bufferSize);
			$length   -= strlen($contents);

			$this->emitBuffer($contents);

			if(connection_status() !== CONNECTION_NORMAL){
				break; // @codeCoverageIgnore
			}
		}

		if($length > 0 && !$this->body->eof()){
			$this->emitBuffer($this->body->read($length));
		}

	}

}
