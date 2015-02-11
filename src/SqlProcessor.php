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
						return $this->processModifier($matches[1], $args[++$j]);

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


	/**
	 * @param  string $type
	 * @param  mixed  $value
	 * @return string
	 */
	public function processModifier($type, $value)
	{
		if ($type === 'any') {
			$type = $this->getValueModifier($value);
		}

		$len = strlen($type);
		if ($type[$len-1] === ']') {
			if (!is_array($value)) {
				throw new InvalidArgumentException("Modifier %$type expects value to be array, " . gettype($value) . " given.");
			} elseif ($type === 'values[]') {
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
		$type2 = $isNullable ? substr($type, 0, -1) : $type;

		if ($value === NULL) {
			if (!$isNullable) {
				throw new InvalidArgumentException("Modifier %$type does not allow NULL value, use modifier %{$type}? instead.");
			}
			return 'NULL';

		} elseif ($type2 === 's') {
			if (!is_string($value)) {
				throw new InvalidArgumentException("Modifier %$type expects value to be string, " . gettype($value) . " given.");
			}
			return $this->driver->convertToSql($value, IDriver::TYPE_STRING);

		} elseif ($type2 === 'b') {
			if (!is_bool($value)) {
				throw new InvalidArgumentException("Modifier %$type expects value to be bool, " . gettype($value) . " given.");
			}
			return $this->driver->convertToSql($value, IDriver::TYPE_BOOL);

		} elseif ($type2 === 'i') {
			if (!is_int($value) && (!is_string($value) || !preg_match('#^-?[1-9][0-9]*+\z#', $value))) {
				throw new InvalidArgumentException("Modifier %$type expects value to be int, " . gettype($value) . " given.");
			}
			return (string) $value;

		} elseif ($type2 === 'f') {
			if (!is_float($value)) {
				throw new InvalidArgumentException("Modifier %$type expects value to be float, " . gettype($value) . " given.");
			} elseif (!is_finite($value)) {
				throw new InvalidArgumentException("Modifier %$type expects value to be finite float, $value given.");
			}
			return ($tmp = json_encode($value)) . (strpos($tmp, '.') === FALSE ? '.0' : '');

		} elseif ($type2 === 'dt') {
			if (!$value instanceof \DateTime && !$value instanceof \DateTimeImmutable) {
				throw new InvalidArgumentException("Modifier %$type expects value to be DateTime, " . gettype($value) . " given.");
			}
			return $this->driver->convertToSql($value, IDriver::TYPE_DATETIME);

		} elseif ($type2 === 'dts') {
			if (!$value instanceof \DateTime && !$value instanceof \DateTimeImmutable) {
				throw new InvalidArgumentException("Modifier %$type expects value to be DateTime, " . gettype($value) . " given.");
			}
			return $this->driver->convertToSql($value, IDriver::TYPE_DATETIME_SIMPLE);

		} else {
			throw new InvalidArgumentException("Unknown modifier '%{$type}'.");
		}
	}


	protected function processValueArray($value, $type)
	{
		$values = [];
		$subType = substr($type, 0, -2);
		foreach ($value as $subValue) {
			$values[] = $this->processModifier($subType, $subValue);
		}

		return '(' . implode(', ', $values) . ')';
	}


	protected function processValueSet($value)
	{
		$values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$values[] = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER) . ' = '
				. $this->processModifier(isset($key[1]) ? $key[1] : $this->getValueModifier($val), $val);
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
				$subValues[] = $this->processModifier(isset($key[1]) ? $key[1] : $this->getValueModifier($val), $val);
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
			$values[] = $this->processModifier(isset($key[1]) ? $key[1] : $this->getValueModifier($val), $val);
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

				$exp .= $this->processModifier($modifier, $val);
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
		} elseif ($value instanceof \DateTime || $value instanceof \DateTimeImmutable) {
			return 'dt';
		} else {
			throw new InvalidArgumentException("Modifier %any can handle pretty much anything but not " . gettype($value) . ".");
		}
	}

}
