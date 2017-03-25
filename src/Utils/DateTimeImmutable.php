<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;


class DateTimeImmutable extends \DateTimeImmutable
{
	public function __toString()
	{
		return $this->format('c');
	}


	public function setTimestamp($timestamp)
	{
		$zone = $this->getTimezone();
		$datetime = new static('@' . $timestamp);
		return $datetime->setTimezone($zone);
	}
}
