<?php
/**
 * Class UtilTestAbstract
 *
 * @created      15.03.2024
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2024 smiley
 * @license      MIT
 */

declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils;

use chillerlan\PHPUnitHttp\HttpFactoryTrait;
use PHPUnit\Framework\TestCase;
use Throwable;
use function realpath;

/**
 *
 */
abstract class UtilTestAbstract extends TestCase{
	use HttpFactoryTrait;

	protected const CACERT = __DIR__.'/cacert.pem';

	protected function setUp():void{
		try{
			$this->initFactories(realpath($this::CACERT));
		}
		catch(Throwable $e){
			$this->markTestSkipped('unable to init http factories: '.$e->getMessage());
		}
	}

}
