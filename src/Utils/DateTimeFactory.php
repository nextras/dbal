<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;

use DateTimeZone;


class DateTimeFactory
{

	public static function from($time, DateTimeZone $timezone = NULL)
	{
		if ($time instanceof \DateTime || $time instanceof \DateTimeInterface) {
			$datetime = new DateTime($time->format('Y-m-d H:i:s'), $time->getTimezone());

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
