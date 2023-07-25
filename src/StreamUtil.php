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

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;
use function in_array;
use function min;
use function preg_match;
use function str_contains;
use function strlen;
use function substr;

/**
 *
 */
final class StreamUtil{

	/**
	 * Checks whether the given mode allows reading and writing
	 */
	public static function modeAllowsReadWrite(string $mode):bool{
		return str_contains(self::validateMode($mode), '+');
	}

	/**
	 * Checks whether the given mode allows only reading
	 */
	public static function modeAllowsReadOnly(string $mode):bool{
		$mode = self::validateMode($mode);

		return $mode[0] === 'r' && !str_contains($mode, '+');
	}

	/**
	 * Checks whether the given mode allows only writing
	 */
	public static function modeAllowsWriteOnly(string $mode):bool{
		$mode = self::validateMode($mode);

		return in_array($mode[0], ['a', 'c', 'w', 'x']) && !str_contains($mode, '+');
	}

	/**
	 * Checks whether the given mode allows reading
	 */
	public static function modeAllowsRead(string $mode):bool{
		$mode = self::validateMode($mode);

		return $mode[0] === 'r' || (in_array($mode[0], ['a', 'c', 'w', 'x']) && str_contains($mode, '+'));
	}

	/**
	 * Checks whether the given mode allows writing
	 */
	public static function modeAllowsWrite(string $mode):bool{
		$mode = self::validateMode($mode);

		return in_array($mode[0], ['a', 'c', 'w', 'x']) || ($mode[0] === 'r' && str_contains($mode, '+'));
	}

	/**
	 * Checks if the given mode is valid for fopen().
	 * Returns the first 15 characters, throws if that string doesn't match the pattern.
	 *
	 * Note: we don't care where the modifier flags are in the string, what matters is that the first character
	 * is one of "acrwx" and the rest may contain one of "bet+" from 2nd position onwards, so "aaaaaaaaaaaaaa+b" is valid.
	 *
	 * The documentation of fopen() says that the text-mode translation flag (b/t) should be added as last character,
	 * however, it doesn't matter as PHP internally only reads the mode from the first character and 15 characters total.
	 * and does a strchr() on it for the flags, so technically "rb+" is equivalent to "r+b" and "rrrbbbb++".
	 * Also, some libraries allow a mode "rw" which is wrong and just falls back to "r" - see above. (looking at you, Guzzle)
	 *
	 * gzopen() adds a bunch of other flags that are hardly documented, so we'll ignore these until we get a full list.
	 *
	 * @see https://www.php.net/manual/en/function.fopen
	 * @see https://www.php.net/manual/en/function.gzopen.php
	 * @see https://stackoverflow.com/a/44483367/3185624
	 * @see https://github.com/php/php-src/blob/6602ddead5c81fb67ebf2b21c32b58aa1de67699/main/streams/plain_wrapper.c#L71-L121
	 * @see https://github.com/guzzle/psr7/blob/815698d9f11c908bc59471d11f642264b533346a/src/Stream.php#L19
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function validateMode(string $mode):string{
		$mode = substr($mode, 0, 15);

		if(!preg_match('/^[acrwx]+[befht+\d]*$/', $mode)){ // [bet+]*
			throw new InvalidArgumentException('invalid fopen mode: '.$mode);
		}

		return $mode;
	}

	/**
	 * Reads the content from a stream and make sure we rewind
	 *
	 * Returns the stream content as a string, null if an error occurs, e.g. the StreamInterface throws.
	 */
	public static function getContents(StreamInterface $stream):?string{

		// rewind before read...
		if($stream->isSeekable()){
			$stream->rewind();
		}

		try{
			$data = $stream->isReadable()
				// stream is readable - great!
				? $stream->getContents()
				// try the __toString() method
				// there's a chance the stream is implemented in such a way (might throw)
				: $stream->__toString(); // @codeCoverageIgnore
		}
		catch(Throwable $e){
			return null;
		}

		// ...and after
		if($stream->isSeekable()){
			$stream->rewind();
		}

		return $data;
	}

	/**
	 * Copies a stream to another stream, starting from the current position of the source stream,
	 * reading to the end or until the given maxlength is hit.
	 *
	 * Throws if the source is not readable or the destination not writable.
	 *
	 * @throws \RuntimeException
	 */
	public static function copyToStream(StreamInterface $source, StreamInterface $destination, int $maxLength = null):int{

		if(!$source->isReadable() || !$destination->isWritable()){
			throw new RuntimeException('$source must be readable and $destination must be writable');
		}

		$remaining = ($maxLength ?? ($source->getSize() - $source->tell()));
		$bytesRead = 0;

		while($remaining > 0 && !$source->eof()){
			$chunk      = $source->read(min(8192, $remaining));
			$length     = strlen($chunk);
			$bytesRead += $length;

			if($length === 0){
				break;
			}

			$remaining -= $length;
			$destination->write($chunk);
		}

		return $bytesRead;
	}

}
