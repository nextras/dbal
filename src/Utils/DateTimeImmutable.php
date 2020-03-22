<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;


final class DateTimeImmutable extends \DateTimeImmutable
{
	public function __toString(): string
	{
		return $this->format('c');
	}


	/**
	 * @param int $timestamp
	 * @return static
	 */
	public function setTimestamp($timestamp): self
	{
		$zone = $this->getTimezone();
		$datetime = new static('@' . (string) $timestamp);
		return $datetime->setTimezone($zone);
	}
}
