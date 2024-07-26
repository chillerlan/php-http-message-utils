<?php
/**
 * Interface ResponseEmitterInterface
 *
 * @created      22.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTP\Utils\Emitter;

interface ResponseEmitterInterface{

	/**
	 * Emits a PSR-7 response.
	 */
	public function emit():void;

}
