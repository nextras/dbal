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

	/** @var array (name => [supports ?, supports [], expected type]) */
	protected $modifiers = [
		// expressions
		's' => [TRUE, TRUE, 'string'],
		'i' => [TRUE, TRUE, 'int'],
		'f' => [TRUE, TRUE, 'float'],
		'b' => [TRUE, TRUE, 'bool'],
		'dt' => [TRUE, TRUE, 'DateTime'],
		'dts' => [TRUE, TRUE, 'DateTime'],
		'any' => [TRUE, TRUE, 'pretty much anything'],
		'and' => [FALSE, FALSE, 'array'],
		'or' => [FALSE, FALSE, 'array'],

		// SQL constructs
		'table' => [FALSE, TRUE, 'string'],
		'column' => [FALSE, TRUE, 'string'],
		'values' => [FALSE, TRUE, 'array'],
		'set' => [FALSE, FALSE, 'array'],
		'raw' => [FALSE, FALSE, 'string'],
		'ex' => [FALSE, FALSE, 'array'],
	];


	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
	}


	/**
	 * @param  mixed[]
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
		$valueType = gettype($value);
		switch ($valueType[0]) {
			case 's': // string
				switch ($type) {
					case 's':
					case 's?':
					case 'any':
					case 'any?':
						return $this->driver->convertToSql($value, IDriver::TYPE_STRING);

					case 'i':
					case 'i?':
						if (!preg_match('#^-?[1-9][0-9]*+\z#', $value)) {
							$this->throwInvalidValueTypeException($type, $value, 'int'); // TODO!
						}
						return (string) $value;

					case 'table':
					case 'column':
						if ($value === '*') {
							$this->throwWrongModifierException($type, $value, "{$type}[]");
						}
						return $this->driver->convertToSql($value, IDriver::TYPE_IDENTIFIER);

					case 'raw':
						return $value;
				}

			break;
			case 'i': // integer
				switch ($type) {
					case 'i':
					case 'i?':
					case 'any':
					case 'any?':
						return (string) $value;
				}

			break;
			case 'd': // double
				switch ($type) {
					case 'f':
					case 'f?':
					case 'any':
					case 'any?':
						if (!is_finite($value)) {
							$this->throwInvalidValueTypeException($type, $value, 'finite float');
						}
						return ($tmp = json_encode($value)) . (strpos($tmp, '.') === FALSE ? '.0' : '');
				}

			break;
			case 'b': // boolean
				switch ($type) {
					case 'b':
					case 'b?':
					case 'any':
					case 'any?':
						return $this->driver->convertToSql($value, IDriver::TYPE_BOOL);
				}

			break;
			case 'N': // NULL
				switch ($type) {
					case 'any?':
					case 's?':
					case 'i?':
					case 'f?':
					case 'b?':
					case 'dt?':
					case 'dts?':
						return 'NULL';
				}

			break;
			case 'o': // object
				switch ($type) {
					case 'dt':
					case 'dt?':
					case 'any':
					case 'any?':
						if (!$value instanceof \DateTime && !$value instanceof \DateTimeImmutable) {
							$this->throwInvalidValueTypeException($type, $value, 'DateTime');
						}
						return $this->driver->convertToSql($value, IDriver::TYPE_DATETIME);

					case 'dts':
					case 'dts?':
						if (!$value instanceof \DateTime && !$value instanceof \DateTimeImmutable) {
							$this->throwInvalidValueTypeException($type, $value, 'DateTime');
						}
						return $this->driver->convertToSql($value, IDriver::TYPE_DATETIME_SIMPLE);
				}

			break;
			case 'a': // array
				switch ($type) {
					case 'any?[]': // micro-optimization
						return $this->processArray($type, $value);

					case 'and':
					case 'or':
						return $this->processWhere($type, $value);

					case 'values':
						return $this->processValues($type, $value);

					case 'values[]':
						return $this->processMultiValues($type, $value);

					case 'set':
						return $this->processSet($type, $value);

					case 'ex':
						return $this->process($value);
				}

				if (substr($type, -1) === ']') {
					$baseType = rtrim($type, '[]?');
					if (isset($this->modifiers[$baseType]) && $this->modifiers[$baseType][1]) {
						return $this->processArray($type, $value);
					}
				}
		}

		$baseType = rtrim($type, '[]?');
		$typeNullable = strrpos($type, '?');
		$typeArray = substr($type, -2) === '[]';
		if (!isset($this->modifiers[$baseType])) {
			throw new InvalidArgumentException("Unknown modifier %$type.");

		} elseif (($typeNullable && !$this->modifiers[$baseType][0]) || ($typeArray && !$this->modifiers[$baseType][1])) {
			throw new InvalidArgumentException("Modifier %$baseType does not have %$type variant.");

		} elseif ($typeArray) {
			$this->throwInvalidValueTypeException($type, $value, 'array');

		} elseif ($value === NULL && !$typeNullable && $this->modifiers[$baseType][0]) {
			$this->throwWrongModifierException($type, $value, "$type?");

		} elseif (is_array($value) && !$typeArray && $this->modifiers[$baseType][1]) {
			$this->throwWrongModifierException($type, $value, "{$type}[]");

		} else {
			$this->throwInvalidValueTypeException($type, $value, $this->modifiers[$baseType][2]);
		}
	}


	/**
	 * @param  string $type
	 * @param  mixed  $value
	 * @param  string $expectedType
	 * @return void
	 */
	protected function throwInvalidValueTypeException($type, $value, $expectedType)
	{
		$actualType = is_object($value) ? get_class($value) : (is_float($value) && !is_finite($value) ? $value : gettype($value));
		throw new InvalidArgumentException("Modifier %$type expects value to be $expectedType, $actualType given.");
	}


	/**
	 * @param  string $type
	 * @param  mixed  $value
	 * @param  string $hint
	 * @return void
	 */
	protected function throwWrongModifierException($type, $value, $hint)
	{
		$valueLabel = is_scalar($value) ? var_export($value, TRUE) : gettype($value);
		throw new InvalidArgumentException("Modifier %$type does not allow $valueLabel value, use modifier %$hint instead.");
	}


	/**
	 * @param  string $type
	 * @param  array  $value
	 * @return string
	 */
	protected function processArray($type, array $value)
	{
		$values = [];
		$subType = substr($type, 0, -2);
		foreach ($value as $subValue) {
			$values[] = $this->processModifier($subType, $subValue); // TODO: limited subset to s, i, f, b, dt, dts, any, table, column + NULLABLE
		}

		return '(' . implode(', ', $values) . ')';
	}


	/**
	 * @param  string $type
	 * @param  array  $value
	 * @return string
	 */
	protected function processSet($type, array $value)
	{
		$values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$column = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER);
			$expr = $this->processModifier(isset($key[1]) ? $key[1] : $this->getValueModifier($val), $val); // TODO: limited subset to anything
			$values[] = "$column = $expr";
		}

		return implode(', ', $values);
	}


	/**
	 * @param  string $type
	 * @param  array  $value
	 * @return string
	 */
	protected function processMultiValues($type, array $value)
	{
		$keys = $values = [];
		foreach (array_keys($value[0]) as $key) {
			$keys[] = $this->driver->convertToSql(explode('%', $key, 2)[0], IDriver::TYPE_IDENTIFIER);
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


	/**
	 * @param  string $type
	 * @param  array  $value
	 * @return string
	 */
	private function processValues($type, array $value)
	{
		$keys = $values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$keys[] = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER);
			$values[] = $this->processModifier(isset($key[1]) ? $key[1] : $this->getValueModifier($val), $val);
		}

		return '(' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
	}


	/**
	 * @param  string $type
	 * @param  array  $value
	 * @return string
	 */
	private function processWhere($type, array $value)
	{
		if (count($value) === 0) {
			return '1=1';
		}

		$operands = [];
		foreach ($value as $_key => $val) {
			if (is_int($_key)) {
				if (!is_array($val)) {
					throw new InvalidArgumentException('Item value with numeric index has to be an array.');
				}
				$operands[] = '(' . $this->process($val) . ')';

			} else {
				$key = explode('%', $_key, 2);
				$operand = $this->driver->convertToSql($key[0], IDriver::TYPE_IDENTIFIER);

				$modifier = isset($key[1]) ? $key[1] : $this->getValueModifier($val);
				$last = substr($modifier, -1);
				if ($last === '?' && $val === NULL) {
					$operand .= ' IS NULL';
				} elseif ($last === ']') {
					$operand .= ' IN ' . $this->processArray($modifier, $val);
				} else {
					$operand .= ' = ' . $this->processModifier($modifier, $val);
				}
				$operands[] = $operand;
			}
		}

		return implode($type === 'and' ? ' AND ' : ' OR ', $operands);
	}


	/**
	 * @param  mixed $value
	 * @return string
	 */
	private function getValueModifier($value)
	{
		$type = is_object($value) ? get_class($value) : gettype($value);
		switch ($type) {
			case 'string':
				return 's';
			case 'integer':
				return 'i';
			case 'double':
				return 'f';
			case 'boolean':
				return 'b';
			case 'array':
				return 'any?[]';
			case 'NULL':
				return 'any?';
			case 'DateTime':
			case 'DateTimeImmutable':
				return 'dt';
			default:
				throw new InvalidArgumentException("Modifier %any can handle pretty much anything but not " . $type . ".");
		}
	}

}
