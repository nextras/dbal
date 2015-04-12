<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace  Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriver;


class SqlProcessor
{
	/** @var IDriver */
	private $driver;

	/** @var LazyHashMap */
	private $identifiers;

	/** @var array (name => [supports ?, supports [], expected type]) */
	protected $modifiers = [
		// expressions
		's' => [TRUE, TRUE, 'string'],
		'i' => [TRUE, TRUE, 'int'],
		'f' => [TRUE, TRUE, '(finite) float'],
		'b' => [TRUE, TRUE, 'bool'],
		'dt' => [TRUE, TRUE, 'DateTime'],
		'dts' => [TRUE, TRUE, 'DateTime'],
		'di' => [TRUE, TRUE, 'DateInterval'],
		'any' => [FALSE, FALSE, 'pretty much anything'],
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
		$this->identifiers = new LazyHashMap(function($key) {
			return $this->driver->convertToSql($key, IDriver::TYPE_IDENTIFIER);
		});
	}


	/**
	 * @param  mixed[]
	 * @return string
	 */
	public function process(array $args)
	{
		$last = count($args) - 1;
		$fragments = [];

		for ($i = 0, $j = 0; $j <= $last; $j++) {
			if (!is_string($args[$j])) {
				throw new InvalidArgumentException($j === 0
					? 'Query fragment must be string.'
					: "Redundant query parameter or missing modifier in query fragment '$args[$i]'."
				);
			}

			$i = $j;
			$fragments[] = preg_replace_callback(
				'#%(\??+\w++(?:\[\]){0,2}+)|(%%)|\[(.+?)\]#S', // %modifier | %% | [identifier]
				function ($matches) use ($args, &$j, $last) {
					if ($matches[1] !== '') {
						if ($j === $last) {
							throw new InvalidArgumentException("Missing query parameter for modifier $matches[0].");
						}
						return $this->processModifier($matches[1], $args[++$j]);

					} elseif ($matches[2] !== '') {
						return '%';

					} elseif (!ctype_digit($matches[3])) {
						return $this->identifiers->{$matches[3]};

					} else {
						return "[$matches[3]]";
					}
				},
				$args[$i]
			);

			if ($i === $j && $j !== $last) {
				throw new InvalidArgumentException("Redundant query parameter or missing modifier in query fragment '$args[$i]'.");
			}
		}

		return implode(' ', $fragments);
	}


	/**
	 * @param  string $type
	 * @param  mixed  $value
	 * @return string
	 */
	public function processModifier($type, $value)
	{
		switch (gettype($value)) {
			case 'string':
				switch ($type) {
					case 'any':
					case 's':
					case '?s':
						return $this->driver->convertToSql($value, IDriver::TYPE_STRING);

					case 'i':
					case '?i':
						if (!preg_match('#^-?[1-9][0-9]*+\z#', $value)) {
							break;
						}
						return (string) $value;

					case 'table':
					case 'column':
						if ($value === '*') {
							$this->throwWrongModifierException($type, $value, "{$type}[]");
						}
						return $this->identifiers->$value;

					case 'raw':
						return $value;
				}

			break;
			case 'integer':
				switch ($type) {
					case 'any':
					case 'i':
					case '?i':
						return (string) $value;
				}

			break;
			case 'double':
				if (is_finite($value)) { // database can not handle INF and NAN
					switch ($type) {
						case 'any':
						case 'f':
						case '?f':
							return ($tmp = json_encode($value)) . (strpos($tmp, '.') === FALSE ? '.0' : '');
					}
				}

			break;
			case 'boolean':
				switch ($type) {
					case 'any':
					case 'b':
					case '?b':
						return $this->driver->convertToSql($value, IDriver::TYPE_BOOL);
				}

			break;
			case 'NULL':
				switch ($type) {
					case 'any':
					case '?s':
					case '?i':
					case '?f':
					case '?b':
					case '?dt':
					case '?dts':
					case '?di':
						return 'NULL';
				}

			break;
			case 'object':
				if ($value instanceof \DateTimeImmutable || $value instanceof \DateTime) {
					switch ($type) {
						case 'any':
						case 'dt':
						case '?dt':
							return $this->driver->convertToSql($value, IDriver::TYPE_DATETIME);

						case 'dts':
						case '?dts':
							return $this->driver->convertToSql($value, IDriver::TYPE_DATETIME_SIMPLE);
					}

				} elseif ($value instanceof \DateInterval) {
					switch ($type) {
						case 'any':
						case 'di':
						case '?di':
							return $this->driver->convertToSql($value, IDriver::TYPE_DATE_INTERVAL);
					}

				} elseif (method_exists($value, '__toString')) {
					switch ($type) {
						case 'any':
						case 's':
						case '?s':
							return $this->driver->convertToSql((string) $value, IDriver::TYPE_STRING);
					}
				}

			break;
			case 'array':
				switch ($type) {
					// micro-optimizations
					case 'any':
						return $this->processArray("any[]", $value);

					case 'i[]':
						$i = count($value);
						while ($i-- && is_int($value[$i]));
						if ($i >= 0) break; // fallback to processArray
						return '(' . implode(', ', $value) . ')';

					case 's[]':
						foreach ($value as &$subValue) {
							if (!is_string($subValue)) break 2; // fallback to processArray
							$subValue = $this->driver->convertToSql($subValue, IDriver::TYPE_STRING);
						}
						return '(' . implode(', ', $value) . ')';

					case 'column[]':
						foreach ($value as &$subValue) {
							if (!is_string($subValue)) break 2; // fallback to processArray
							$subValue = $this->identifiers->$subValue;
						}
						return '(' . implode(', ', $value) . ')';

					// normal
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
					$baseType = trim($type, '[]?');
					if (isset($this->modifiers[$baseType]) && $this->modifiers[$baseType][1]) {
						return $this->processArray($type, $value);
					}
				}
		}

		$baseType = trim($type, '[]?');
		$typeNullable = $type[0] === '?';
		$typeArray = substr($type, -2) === '[]';
		if (!isset($this->modifiers[$baseType])) {
			throw new InvalidArgumentException("Unknown modifier %$type.");

		} elseif (($typeNullable && !$this->modifiers[$baseType][0]) || ($typeArray && !$this->modifiers[$baseType][1])) {
			throw new InvalidArgumentException("Modifier %$baseType does not have %$type variant.");

		} elseif ($typeArray) {
			$this->throwInvalidValueTypeException($type, $value, 'array');

		} elseif ($value === NULL && !$typeNullable && $this->modifiers[$baseType][0]) {
			$this->throwWrongModifierException($type, $value, "?$type");

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
		$actualType = $this->getVariableTypeName($value);
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
		$subType = substr($type, 0, -2);
		foreach ($value as &$subValue) {
			$subValue = $this->processModifier($subType, $subValue);
		}

		return '(' . implode(', ', $value) . ')';
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
			$column = $this->identifiers->{$key{0}};
			$expr = $this->processModifier(isset($key[1]) ? $key[1] : 'any', $val);
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
			$keys[] = $this->identifiers->{explode('%', $key, 2)[0]};
		}
		foreach ($value as $subValue) {
			$subValues = [];
			foreach ($subValue as $_key => $val) {
				$key = explode('%', $_key, 2);
				$subValues[] = $this->processModifier(isset($key[1]) ? $key[1] : 'any', $val);
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
			$keys[] = $this->identifiers->{$key[0]};
			$values[] = $this->processModifier(isset($key[1]) ? $key[1] : 'any', $val);
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
		foreach ($value as $_key => $subValue) {
			if (is_int($_key)) {
				if (!is_array($subValue)) {
					$subValueType = $this->getVariableTypeName($subValue);
					throw new InvalidArgumentException("Modifier %$type requires items with numeric index to be array, $subValueType given.");
				}

				$operand = '(' . $this->process($subValue) . ')';

			} else {
				$key = explode('%', $_key, 2);
				$column = $this->identifiers->{$key[0]};
				$subType = isset($key[1]) ? $key[1] : 'any';

				if ($subValue === NULL) {
					$op = ' IS ';
				} elseif (is_array($subValue) && $subType !== 'ex') {
					$op = ' IN ';
				} else {
					$op = ' = ';
				}

				$operand = $column . $op . $this->processModifier($subType, $subValue);
			}

			$operands[] = $operand;
		}

		return implode($type === 'and' ? ' AND ' : ' OR ', $operands);
	}


	/**
	 * @param $value
	 * @return float|string
	 */
	protected function getVariableTypeName($value)
	{
		return is_object($value) ? get_class($value) : (is_float($value) && !is_finite($value) ? $value : gettype($value));
	}

}
