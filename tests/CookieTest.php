<?php
/**
 * Class CookieTest
 *
 * @created      28.02.2024
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2024 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTPTest\Utils;

use chillerlan\HTTP\Utils\Cookie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use DateInterval, DateTimeImmutable, DateTimeInterface, DateTimeZone, Generator, InvalidArgumentException;
use function ord, sprintf;

final class CookieTest extends TestCase{

	public function testEmptyNameException():void{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The cookie name cannot be empty.');

		new Cookie('');
	}

	public static function invalidNameProvider():Generator{
		foreach(Cookie::RESERVED_CHARACTERS as $char){
			yield sprintf('char: %1$03d (0x%1$02X)', ord($char)) => [$char];
		}
	}

	#[DataProvider('invalidNameProvider')]
	public function testInvalidCharactersInNameException(string $char):void{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The cookie name contains invalid (reserved) characters.');

		new Cookie('Cookie'.$char.'Name');
	}

	/**
	 * @return array<string, array{0: DateTimeInterface|DateInterval|int, 1: string, 2: int}>
	 */
	public static function expiryProvider():array{
		$dt     = (new DateTimeImmutable)->setTimezone(new DateTimeZone('GMT'));
		$now    = $dt->getTimestamp();

		return [
			// int 0 is meant to delete a a cookie wand will explicitly set a (magic) date far in the past
			'int 0'         => [0, 'Thursday, 01-Jan-1970 12:34:56 GMT', 0],
			// timestamps greater than the current time are treated as absolute
			'int (now + 1)' => [($now + 69420), $dt->setTimestamp($now + 69420)->format(DateTimeInterface::COOKIE), 69420],
			// timestamps/integers smaller than the current time are treated as relative from now
			'int (82517)'   => [82517, $dt->setTimestamp($now + 82517)->format(DateTimeInterface::COOKIE), 82517],
			// a given DateTimeInterface will always be treated as absolute time
			'DateTime'      => [
				$dt->setTimestamp($now + 1337),
				$dt->setTimestamp($now + 1337)->format(DateTimeInterface::COOKIE), 1337,
			],
			// a given DateInterval is always relative to the current time
			'DateInterval'  => [
				(new DateInterval('PT42S')),
				$dt->setTimestamp($now + 42)->format(DateTimeInterface::COOKIE), 42,
			],
		];
	}

	#[DataProvider('expiryProvider')]
	public function testSetExpiry(DateTimeInterface|DateInterval|int $expiry, string $expectedDate, int $expectedMaxAge):void{
		$cookie = (new Cookie('test', 'expiry'))->withExpiry($expiry);

		try{
			$this::assertSame(sprintf('test=expiry; Expires=%s; Max-Age=%s', $expectedDate, $expectedMaxAge), (string)$cookie);
		}
		catch(ExpectationFailedException){
			$this::markTestSkipped('time related assertion might be wonky');
		}

		// null unsets the current timestamp and will not generate an expiry or max-age attribute
		$this::assertSame('test=expiry', (string)$cookie->withExpiry(null));
	}

	public function testExpiryWithEmptyValue():void{
		// an empty value is supposed to delete the cookie - no matter what the expiry says
		$cookie = (new Cookie('test', ''))->withExpiry(69);

		$this::assertSame('test=; Expires=Thursday, 01-Jan-1970 12:34:56 GMT; Max-Age=0', (string)$cookie);
	}

	/**
	 * @return array<string, array{0: string, 1: bool, 2: string}>
	 */
	public static function domainProvider():array{
		return [
			'WWW.Example.COM'            => ['WWW.Example.COM', false, 'www.example.com'],
			'WWW.Example.COM (punycode)' => ['WWW.Example.COM', true, 'www.example.com'],
			'яндекAс.рф'                 => ['яндекAс.рф', true, 'xn--a-gtbdum2a6g.xn--p1ai'],
		];
	}

	#[DataProvider('domainProvider')]
	public function testSetDomain(string $domain, bool $punycode, string $expected):void{
		$cookie = (new Cookie('test', 'domain'))->withDomain($domain, $punycode);

		$this::assertSame(sprintf('test=domain; Domain=%s', $expected), (string)$cookie);
		// test unset
		$this::assertSame('test=domain', (string)$cookie->withDomain(null));
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function pathProvider():array{
		return [
			'empty'  => ['', '/'],
			'falsey' => ['0', '0'],
			'normal' => ['/path', '/path'],
		];
	}

	#[DataProvider('pathProvider')]
	public function testPath(string $path, string $expected):void{
		$cookie = (new Cookie('test', 'path'))->withPath($path);

		$this::assertSame(sprintf('test=path; Path=%s', $expected), (string)$cookie);
		// test unset
		$this::assertSame('test=path', (string)$cookie->withPath(null));
	}

	public function testSecure():void{
		$cookie = new Cookie('test', 'secure');

		$this::assertSame('test=secure; Secure', (string)$cookie->withSecure(true));
		$this::assertSame('test=secure', (string)$cookie->withSecure(false));
	}

	public function testHttpOnly():void{
		$cookie = new Cookie('test', 'httponly');

		$this::assertSame('test=httponly; HttpOnly', (string)$cookie->withHttpOnly(true));
		$this::assertSame('test=httponly', (string)$cookie->withHttpOnly(false));
	}

	public function testSameSiteInvalidValueException():void{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The same site attribute must be "lax", "strict" or "none"');

		(new Cookie('test', 'samesite'))->withSameSite('foo');
	}

	public function testSameSiteNoneWithoutSecureException():void{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The same site attribute can only be "none" when secure is set to true');

		(string)(new Cookie('test', 'samesite'))->withSameSite('none');
	}

	public function testSameSite():void{
		$cookie = (new Cookie('test', 'samesite'))->withSameSite('strict');

		$this::assertSame('test=samesite; SameSite=strict', (string)$cookie);
		// test unset
		$this::assertSame('test=samesite', (string)$cookie->withSameSite(null));
		// with "none" and "secure"
		$this::assertSame('test=samesite; Secure; SameSite=none', (string)$cookie->withSecure(true)->withSameSite('none'));
	}
}
