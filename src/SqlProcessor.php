<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace  Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriverProvider;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exceptions\InvalidArgumentException;


class SqlProcessor
{
	/** @var IDriverProvider */
	private $driver;


	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
	}


	public function process(array $args)
	{
		$last = count($args) - 1;
		$query = '';

		for ($i = 0, $j = 0; $j <= $last; $j++) {
			if (!is_string($args[$j])) {
				if ($j === 0) {
					throw new InvalidArgumentException('Query fragment must be string.');
				} else {
					throw new InvalidArgumentException("Redundant query parameter or missing modifier in query fragment '$args[$i]'.");
				}
			}

			$i = $j;
			if ($i > 0) {
				$query .= ' ';
			}

			$query .= preg_replace_callback(
				'#%(\w++\??+(?:\[\])?+)|(\[.+?\])#',
				function ($matches) use ($args, &$j, $last) {
					if ($matches[1] !== '') {
						if ($j === $last) {
							throw new InvalidArgumentException("Missing query parameter for modifier $matches[0].");
						}
						return $this->processValue($args[++$j], $matches[1]);

					} else {
						return $this->driver->convertToSql(substr($matches[2], 1, -1), IDriver::TYPE_IDENTIFIER);
					}
				},
				$args[$i]
			);

			if ($i === $j && $j !== $last) {
				throw new InvalidArgumentException("Redundant query parameter or missing modifier in query fragment '$args[$i]'.");
			}
		}

		return $query;
	}


	protected function processValue($value, $type)
	{
		$len = strlen($type);

		if (isset($type[$len-2], $type[$len-1]) && $type[$len-2] === '[' && $type[$len-1] === ']') {
			$values = [];
			$subType = substr($type, 0, -2);
			foreach ((array) $value as $subValue) {
				$values[] = $this->processValue($subValue, $subType);
			}

			return '(' . implode(', ', $values) . ')';

		} elseif ($type === 'table' || $type === 'column') {
			return $this->driver->convertToSql($value, IDriver::TYPE_IDENTIFIER);
		}


		$isNullable = isset($type[$len-1]) && $type[$len-1] === '?';
		if ($isNullable) {
			$type = substr($type, 0, -1);
		}

		if ($value === NULL) {
			if (!$isNullable) {
				throw new InvalidArgumentException("NULL value not allowed in '%{$type}' modifier. Use '%{$type}?' modifier.");
			}
			return 'NULL';

		} elseif ($type === 's') {
			return $this->driver->convertToSql($value, IDriver::TYPE_STRING);

		} elseif ($type === 'b') {
			return $this->driver->convertToSql($value, IDriver::TYPE_BOOL);

		} elseif ($type === 'i') {
			return (string) (int) $value;

		} elseif ($type === 'f') {
			return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

		} else {
			throw new InvalidArgumentException("Unknown modifier '%{$type}'.");
		}
	}

}
