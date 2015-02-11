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
				'#%(\w++\??+(?:\[\]){0,2}+)|\[(.+?)\]#', // %modifier | [identifier]
				function ($matches) use ($args, &$j, $last) {
					if ($matches[1] !== '') {
						if ($j === $last) {
							throw new InvalidArgumentException("Missing query parameter for modifier $matches[0].");
						}
						return $this->processValue($args[++$j], $matches[1]);

					} elseif (!ctype_digit($matches[2])) {
						return $this->driver->convertToSql($matches[2], IDriver::TYPE_IDENTIFIER);

					} else {
						return "[$matches[2]]";
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
		if ($type === 'any') {
			$type = $this->getValueModifier($value);
		}

		$len = strlen($type);
		if ($type[$len-1] === ']') {
			if ($type === 'values[]') {
				return $this->processValueMultiValues($value);
			} else {
				return $this->processValueArray($value, $type);
			}

		} elseif ($type === 'table' || $type === 'column') {
			return $this->driver->convertToSql($value, IDriver::TYPE_IDENTIFIER);

		} elseif ($type === 'set') {
			return $this->processValueSet($value);

		} elseif ($type === 'values') {
			return $this->processValueValues($value);

		} elseif ($type === 'and' || $type === 'or') {
			return $this->processValueWhere($value, $type);

		} elseif ($type === 'raw') {
			return $value;
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

		} elseif ($type === 'dt') {
			return $this->driver->convertToSql($value, IDriver::TYPE_DATETIME);

		} elseif ($type === 'dts') {
			return $this->driver->convertToSql($value, IDriver::TYPE_DATETIME_SIMPLE);

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
				. $this->processValue($val, isset($key[1]) ? $key[1] : $this->getValueModifier($val));
		}

		return implode(', ', $values);
	}


	private function processValueMultiValues($value)
	{
		$keys = $values = [];
		foreach ($value[0] as $_key => $val) {
			$key = explode('%', $_key, 2);
			$keys[] = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER);
		}
		foreach ($value as $subValue) {
			$subValues = [];
			foreach ($subValue as $_key => $val) {
				$key = explode('%', $_key, 2);
				$subValues[] = $this->processValue($val, isset($key[1]) ? $key[1] : $this->getValueModifier($val));
			}
			$values[] = '(' . implode(', ', $subValues) . ')';
		}

		return '(' . implode(', ', $keys) . ') VALUES ' . implode(', ', $values);
	}


	private function processValueValues($value)
	{
		$keys = $values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$keys[] = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER);
			$values[] = $this->processValue($val, isset($key[1]) ? $key[1] : $this->getValueModifier($val));
		}

		return '(' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
	}


	private function processValueWhere(array $value, $type)
	{
		if (count($value) === 0) {
			return '1=1';
		}

		$values = [];
		foreach ($value as $_key => $val) {
			if (is_int($_key)) {
				if (!is_array($val)) {
					throw new InvalidArgumentException('Item value with numeric index has to be an array.');
				}
				$values[] = '(' . $this->process($val) . ')';

			} else {
				$key = explode('%', $_key, 2);
				$exp = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER);

				$modifier = isset($key[1]) ? $key[1] : $this->getValueModifier($val);
				$len = strlen($modifier);
				if ($modifier[$len - 1] === '?' && $val === NULL) {
					$exp .= ' IS ';
				} elseif ($modifier[$len - 1] === ']') {
					$exp .= ' IN ';
				} else {
					$exp .= ' = ';
				}

				$exp .= $this->processValue($val, $modifier);
				$values[] = $exp;
			}
		}

		return implode($type === 'and' ? ' AND ' : ' OR ', $values);
	}


	private function getValueModifier($value)
	{
		if ($value === NULL) {
			return 'any?';
		} elseif (is_array($value)) {
			return 'any[]';
		} elseif (is_string($value)) {
			return 's';
		} elseif (is_bool($value)) {
			return 'b';
		} elseif (is_int($value)) {
			return 'i';
		} elseif (is_float($value)) {
			return 'f';
		} elseif ($value instanceof \DateTime) {
			return 'dt';
		} else {
			return 's';
		}
	}

}
