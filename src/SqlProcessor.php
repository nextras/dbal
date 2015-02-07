<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace  Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exceptions\InvalidArgumentException;


class SqlProcessor
{
	/** @var IDriver */
	private $driver;


	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
	}


	/**
	 * @return string
	 */
	public function process(array $args)
	{
		$last = count($args) - 1;
		$query = '';

		for ($i = 0, $j = 0; $j <= $last; $j++) {
			if (!is_string($args[$j])) {
				throw new InvalidArgumentException($j === 0
					? 'Query fragment must be string.'
					: "Redundant query parameter or missing modifier in query fragment '$args[$i]'."
				);
			}

			$i = $j;
			$query .= ($i ? ' ' : '');
			$query .= preg_replace_callback(
				'#%(\w++\??+(?:\[\])?+)|\[(.+?)\]#', // %modifier | [identifier]
				function ($matches) use ($args, &$j, $last) {
					if ($matches[1] !== '') {
						if ($j === $last) {
							throw new InvalidArgumentException("Missing query parameter for modifier $matches[0].");
						}
						return $this->processValue($args[++$j], $matches[1]);

					} else {
						return $this->driver->convertToSql($matches[2], IDriver::TYPE_IDENTIFIER);
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

		if (isset($type[$len-2]) && $type[$len-2] === '[' && $type[$len-1] === ']') {
			return $this->processValueArray($value, $type);

		} elseif ($type === 'table' || $type === 'column') {
			return $this->driver->convertToSql($value, IDriver::TYPE_IDENTIFIER);

		} elseif ($type === 'set') {
			return $this->processValueSet($value);

		} elseif ($type === 'values') {
			return $this->processValueValues($value);
		}


		$isNullable = $type[$len-1] === '?';
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


	protected function processValueArray($value, $type)
	{
		$values = [];
		$subType = substr($type, 0, -2);
		foreach ((array) $value as $subValue) {
			$values[] = $this->processValue($subValue, $subType);
		}

		return '(' . implode(', ', $values) . ')';
	}


	protected function processValueSet($value)
	{
		$values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$values[] = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER) . ' = '
				. $this->processValue($val, isset($key[1]) ? $key[1] : 's');
		}

		return implode(', ', $values);
	}


	private function processValueValues($value)
	{
		$keys = $values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$keys[] = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER);
			$values[] = $this->processValue($val, isset($key[1]) ? $key[1] : 's');
		}

		return '(' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
	}

}
