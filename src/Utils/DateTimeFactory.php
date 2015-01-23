<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;

use DateTime;
use DateTimeZone;


class DateTimeFactory
{

	public static function from($time, DateTimeZone $timezone = NULL)
	{
		if (is_numeric($time)) {
			$datetime = new DateTime("@{$time}");
			$datetime->setTimeZone($timezone ?: new DateTimeZone(date_default_timezone_get()));

		} elseif ($timezone !== NULL) {
			$datetime = new DateTime($time, $timezone);

		} else {
			$datetime = new DateTime($time);
		}

		return $datetime;
	}

}
