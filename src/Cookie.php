<?php
/**
 * Class Cookie
 *
 * @created      27.02.2024
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2024 smiley
 * @license      MIT
 */
declare(strict_types=1);

namespace chillerlan\HTTP\Utils;

use DateInterval, DateTime, DateTimeInterface, DateTimeZone, InvalidArgumentException, RuntimeException;
use function idn_to_ascii, implode, in_array, mb_strtolower, rawurlencode, sprintf, str_replace, strtolower, trim;

/**
 * @see https://datatracker.ietf.org/doc/html/rfc6265#section-4.1
 */
class Cookie{

	public const RESERVED_CHARACTERS = ["\t", "\n", "\v", "\f", "\r", "\x0E", ' ', ',', ';', '='];

	protected string                 $name;
	protected string                 $value;
	protected DateTimeInterface|null $expiry   = null;
	protected int                    $maxAge   = 0;
	protected string|null            $domain   = null;
	protected string|null            $path     = null;
	protected bool                   $secure   = false;
	protected bool                   $httpOnly = false;
	protected string|null            $sameSite = null;

	public function __construct(string $name, string|null $value = null){
		$this->withNameAndValue($name, ($value ?? ''));
	}

	public function __toString():string{
		$cookie = [sprintf('%s=%s', $this->name, $this->value)];

		if($this->expiry !== null){

			if($this->value === ''){
				// set a date in the past to delete the cookie
				$this->withExpiry(0);
			}

			$cookie[] = sprintf('Expires=%s; Max-Age=%s', $this->expiry->format(DateTimeInterface::COOKIE), $this->maxAge);
		}

		if($this->domain !== null){
			$cookie[] = sprintf('Domain=%s', $this->domain);
		}

		if($this->path !== null){
			$cookie[] = sprintf('Path=%s', $this->path);
		}

		if($this->secure === true){
			$cookie[] = 'Secure';
		}

		if($this->httpOnly === true){
			$cookie[] = 'HttpOnly';
		}

		if($this->sameSite !== null){

			if($this->sameSite === 'none' && !$this->secure){
				throw new InvalidArgumentException('The same site attribute can only be "none" when secure is set to true');
			}

			$cookie[] = sprintf('SameSite=%s', $this->sameSite);
		}

		return implode('; ', $cookie);
	}

	/**
	 * @see https://datatracker.ietf.org/doc/html/rfc6265#section-4.1.1
	 * @see https://github.com/symfony/symfony/blob/de93ccde2a1be2a46dbc6e10d5541a0f07e22e33/src/Symfony/Component/HttpFoundation/Cookie.php#L100-L102
	 */
	public function withNameAndValue(string $name, string $value):static{
		$name = trim($name);

		if($name === ''){
			throw new InvalidArgumentException('The cookie name cannot be empty.');
		}

		if(str_replace(static::RESERVED_CHARACTERS, '', $name) !== $name){
			throw new InvalidArgumentException('The cookie name contains invalid (reserved) characters.');
		}

		$this->name  = $name;
		$this->value = rawurlencode(trim($value));

		return $this;
	}

	/**
	 * @see https://datatracker.ietf.org/doc/html/rfc6265#section-4.1.2.1
	 * @see https://datatracker.ietf.org/doc/html/rfc6265#section-4.1.2.2
	 */
	public function withExpiry(DateTimeInterface|DateInterval|int|null $expiry):static{

		if($expiry === null){
			$this->expiry = null;
			$this->maxAge = 0;

			return $this;
		}

		$dt  = (new DateTime)->setTimezone(new DateTimeZone('GMT'));
		$now = $dt->getTimestamp();

		$this->expiry = match(true){
			$expiry instanceof DateTimeInterface => $expiry,
			$expiry instanceof DateInterval      => $dt->add($expiry),
			// 0 is supposed to delete the cookie, set a magic number: 01-Jan-1970 12:34:56
			$expiry === 0                        => $dt->setTimestamp(45296),
			// assuming a relative time interval
			$expiry < $now                       => $dt->setTimestamp($now + $expiry),
			// timestamp in the future (incl. now, which will delete the cookie)
			$expiry >= $now                      => $dt->setTimestamp($expiry),
			default                              => throw new InvalidArgumentException('invalid expiry value'),
		};

		$this->maxAge = ($this->expiry->getTimestamp() - $now);

		if($this->maxAge < 0){
			$this->maxAge = 0;
		}

		return $this;
	}

	/**
	 * @see https://datatracker.ietf.org/doc/html/rfc6265#section-4.1.2.3
	 */
	public function withDomain(string|null $domain, bool|null $punycode = null):static{

		if($domain !== null){
			$domain = mb_strtolower(trim($domain));

			// take care of multibyte domain names (IDN)
			if($punycode === true){
				$domain = idn_to_ascii($domain);

				if($domain === false){
					throw new RuntimeException('Could not convert the given domain to IDN'); // @codeCoverageIgnore
				}
			}
		}

		$this->domain = $domain;

		return $this;
	}

	/**
	 * @see https://datatracker.ietf.org/doc/html/rfc6265#section-4.1.2.4
	 */
	public function withPath(string|null $path):static{

		if($path !== null){
			$path = trim($path);

			if($path === ''){
				$path = '/';
			}
		}

		$this->path = $path;

		return $this;
	}

	/**
	 * @see https://datatracker.ietf.org/doc/html/rfc6265#section-4.1.2.5
	 */
	public function withSecure(bool $secure):static{
		$this->secure = $secure;

		return $this;
	}

	/**
	 * @see https://datatracker.ietf.org/doc/html/rfc6265#section-4.1.2.6
	 */
	public function withHttpOnly(bool $httpOnly):static{
		$this->httpOnly = $httpOnly;

		return $this;
	}

	/**
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value
	 */
	public function withSameSite(string|null $sameSite):static{

		if($sameSite !== null){
			$sameSite = strtolower(trim($sameSite));

			if(!in_array($sameSite, ['lax', 'strict', 'none'], true)){
				throw new InvalidArgumentException('The same site attribute must be "lax", "strict" or "none"');
			}
		}

		$this->sameSite = $sameSite;

		return $this;
	}

}
