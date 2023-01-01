<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


final class DateTimeImmutable extends \DateTimeImmutable implements \Stringable
{
	public function __toString(): string
	{
		return $this->format('c');
	}


	public function setTimestamp(int $timestamp): self
	{
		$zone = $this->getTimezone();
		$datetime = new static('@' . (string) $timestamp);
		if ($zone === false) { // @phpstan-ignore-line
			return $datetime;
		}
		return $datetime->setTimezone($zone);
	}
}
