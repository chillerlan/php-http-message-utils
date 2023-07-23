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

	public const MODES_READ_WRITE = ['a+', 'c+', 'c+b', 'c+t', 'r+' , 'r+b', 'r+t', 'w+' , 'w+b', 'w+t', 'x+' , 'x+b', 'x+t'];
	public const MODES_READ       = [...self::MODES_READ_WRITE, 'r', 'rb', 'rt'];
	public const MODES_WRITE      = [...self::MODES_READ_WRITE, 'a', 'rw', 'w', 'wb'];

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
