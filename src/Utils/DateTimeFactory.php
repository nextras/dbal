<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;

use DateTime;
use DateTimeInterface;
use DateTimeZone;


class DateTimeFactory
{

	public static function from($time, DateTimeZone $timezone = NULL)
	{
		if ($time instanceof DateTime || $time instanceof DateTimeInterface) {
			$datetime = clone $time;

		} elseif (ctype_digit($time)) {
			$datetime = new DateTime("@{$time}");
			if ($timezone === NULL) {
				$timezone = new DateTimeZone(date_default_timezone_get());
			}

		} else {
			$datetime = new DateTime($time);
		}

		if ($timezone !== NULL) {
			$datetime->setTimezone($timezone);
		}

		return $datetime;
	}

}
