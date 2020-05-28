<?php declare(strict_types = 1);

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
