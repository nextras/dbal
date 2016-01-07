<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;


class PostgreArrayConverter
{
	/**
	 * Converts Postgre string to PHP.
	 * @param  string $string
	 * @return array|NULL
	 */
	public static function toPhp($string, $start = 0, & $end = NULL)
	{
		if (empty($string) || $string[0] != '{') {
			return NULL;
		}

		$return = [];
		$inString = FALSE;
		$quote = '';
		$len = strlen($string);
		$value = '';

		for ($i = $start + 1; $i < $len; $i++) {
			$char = $string[$i];

			if ($inString === FALSE) {
				if ($char === '}' || $char === ',') {
					if ($char === '}' && $value === '' && empty($return)) {
						// skip empty values only when it is an empty object
						// e.g.: {} results in [], {,} results in ['', '']
					} else {
						if (ctype_digit($value)) {
							$value = (int) $value;
						} elseif (strcasecmp($value, 'null') === 0) {
							$value = NULL;
						}
						$return[] = $value;
						$value = '';
					}
					if ($char === '}') {
						$end = $i;
						break;
					}

				} elseif ($char === '{') {
					$value = self::toPhp($string, $i, $i);

				} elseif ($char === '"' || $char === "'") {
					$inString = TRUE;
					$quote = $char;

				} else {
					$value .= $char;
				}

			} elseif ($char === $quote) {
				if ($string[$i - 1] == "\\") {
					$value = substr($value, 0, -1) . $char;
				} else {
					$inString = FALSE;
				}

			} else {
				$value .= $char;
			}
		}

		return $return;
	}


	/**
	 * Converts PHP to Postgre string.
	 * @param  array|NULL $array
	 * @return string
	 */
	public static function toSql($array)
	{
		if ($array === NULL) {
			return '';
		}

		settype($array, 'array'); // can be called with a scalar or array
		$result = [];
		foreach ($array as $item) {
			if (is_array($item)) {
				$result[] = self::toSql($item);

			} else {
				if ($item === NULL) {
					$item = 'NULL';
				} elseif (!is_int($item)) { // quote only non-numeric values
					$item = '"' . str_replace('"', '\\"', $item) . '"'; // escape double quote
				}
				$result[] = $item;
			}
		}

		return '{' . implode(',', $result) . '}';
	}
}
