<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use DateTimeInterface;
use DateTimeZone;


/**
 * @internal
 */
class DateTimeHelper
{
	public static function convertToTimezone(DateTimeInterface $value, DateTimeZone $connectionTz): DateTimeInterface
	{
		$valueTimezone = $value->getTimezone();
		assert($valueTimezone !== false); // @phpstan-ignore-line

		if ($valueTimezone->getName() !== $connectionTz->getName()) {
			if ($value instanceof \DateTimeImmutable) {
				return $value->setTimezone($connectionTz);
			} else {
				$value = clone $value;
				$value->setTimezone($connectionTz);
				return $value;
			}
		}

		return $value;
	}
}
