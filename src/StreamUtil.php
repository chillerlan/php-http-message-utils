<?php
/**
 * Class StreamUtil
 *
 * @created      21.07.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */

namespace chillerlan\HTTP\Utils;

use Psr\Http\Message\StreamInterface;

/**
 *
 */
class StreamUtil{

	/**
	 * Reads the content from a stream and make sure we rewind
	 */
	public static function getContents(StreamInterface $stream):string{

		// rewind before read...
		if($stream->isSeekable()){
			$stream->rewind();
		}

		$data = $stream->isReadable()
			? $stream->getContents()
			: $stream->__toString();

		// ...and after
		if($stream->isSeekable()){
			$stream->rewind();
		}

		return $data;
	}

}
