<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace  Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Platforms\IPlatform;


class SqlProcessor
{
	/** @var array (name => [supports ?, supports [], expected type]) */
	protected $modifiers = [
		// expressions
		's' => [true, true, 'string'],
		'json' => [true, true, 'pretty much anything'],
		'i' => [true, true, 'int'],
		'f' => [true, true, '(finite) float'],
		'b' => [true, true, 'bool'],
		'dt' => [true, true, 'DateTime'],
		'dts' => [true, true, 'DateTime'],
		'di' => [true, true, 'DateInterval'],
		'blob' => [true, true, 'blob string'],
		'_like' => [true, false, 'string'],
		'like_' => [true, false, 'string'],
		'_like_' => [true, false, 'string'],
		'any' => [false, false, 'pretty much anything'],
		'and' => [false, false, 'array'],
		'or' => [false, false, 'array'],
		'multiOr' => [false, false, 'array'],

		// SQL constructs
		'table' => [false, true, 'string'],
		'column' => [false, true, 'string'],
		'values' => [false, true, 'array'],
		'set' => [false, false, 'array'],
		'raw' => [false, false, 'string'],
		'ex' => [false, false, 'array'],
	];

	/** @var array (modifier => callable) */
	protected $customModifiers = [];

	/** @var IDriver */
	private $driver;

	/** @var array */
	private $identifiers;

	/** @var IPlatform */
	private $platform;


	public function __construct(IDriver $driver, IPlatform $platform)
	{
		$this->driver = $driver;
		$this->platform = $platform;
	}


	/**
	 * @param callable $callback (mixed $value, string $modifier): mixed
	 */
	public function setCustomModifier(string $modifier, callable $callback)
	{
		$baseModifier = trim($modifier, '[]?');
		if (isset($this->modifiers[$baseModifier])) {
			throw new InvalidArgumentException("Cannot override core modifier '$baseModifier'.");
		}

		$this->customModifiers[$modifier] = $callback;
	}


	/**
	 * @param  mixed[] $args
	 */
	public function process(array $args): string
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
						return $this->identifierToSql($matches[3]);

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
	 * @param  mixed $value
	 */
	public function processModifier(string $type, $value): string
	{
		switch (gettype($value)) {
			case 'string':
				switch ($type) {
					case 'any':
					case 's':
					case '?s':
						return $this->driver->convertStringToSql($value);

					case 'json':
					case '?json':
						return $this->driver->convertJsonToSql($value);

					case 'i':
					case '?i':
						if (!preg_match('#^-?[1-9][0-9]*+\z#', $value)) {
							break;
						}
						return (string) $value;

					case '_like':
						return $this->driver->convertLikeToSql($value, -1);
					case 'like_':
						return $this->driver->convertLikeToSql($value, 1);
					case '_like_':
						return $this->driver->convertLikeToSql($value, 0);

					case 'column':
						if ($value === '*') {
							return '*';
						}
						// intentional pass-through
					case 'table':
						return $this->identifierToSql($value);

					case 'blob':
						return $this->driver->convertBlobToSql($value);

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

					case 'json':
					case '?json':
						return $this->driver->convertJsonToSql($value);
				}

			break;
			case 'double':
				if (is_finite($value)) { // database can not handle INF and NAN
					switch ($type) {
						case 'any':
						case 'f':
						case '?f':
							$tmp = json_encode($value);
							assert(is_string($tmp));
							return $tmp . (strpos($tmp, '.') === false ? '.0' : '');

						case 'json':
						case '?json':
							return $this->driver->convertJsonToSql($value);
					}
				}

			break;
			case 'boolean':
				switch ($type) {
					case 'any':
					case 'b':
					case '?b':
						return $this->driver->convertBoolToSql($value);

					case 'json':
					case '?json':
						return $this->driver->convertJsonToSql($value);
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
					case '?blob':
					case '?json':
						return 'NULL';
				}

			break;
			case 'object':
				if ($type === 'json' || $type === '?json') {
					return $this->driver->convertJsonToSql($value);
				}

				if ($value instanceof \DateTimeImmutable || $value instanceof \DateTime) {
					switch ($type) {
						case 'any':
						case 'dt':
						case '?dt':
							return $this->driver->convertDateTimeToSql($value);

						case 'dts':
						case '?dts':
							return $this->driver->convertDateTimeSimpleToSql($value);
					}

				} elseif ($value instanceof \DateInterval) {
					switch ($type) {
						case 'any':
						case 'di':
						case '?di':
							return $this->driver->convertDateIntervalToSql($value);
					}

				} elseif (method_exists($value, '__toString')) {
					switch ($type) {
						case 'any':
						case 's':
						case '?s':
							return $this->driver->convertStringToSql((string) $value);

						case '_like':
							return $this->driver->convertLikeToSql((string) $value, -1);
						case 'like_':
							return $this->driver->convertLikeToSql((string) $value, 1);
						case '_like_':
							return $this->driver->convertLikeToSql((string) $value, 0);
					}
				}

			break;
			case 'array':
				switch ($type) {
					// micro-optimizations
					case 'any':
						return $this->processArray("any[]", $value);

					case 'i[]':
						foreach ($value as $v) {
							if (!is_int($v)) break 2; // fallback to processArray
						}
						return '(' . implode(', ', $value) . ')';

					case 's[]':
						foreach ($value as &$subValue) {
							if (!is_string($subValue)) break 2; // fallback to processArray
							$subValue = $this->driver->convertStringToSql($subValue);
						}
						return '(' . implode(', ', $value) . ')';

					case 'json':
					case '?json':
						return $this->driver->convertJsonToSql($value);

					// normal
					case 'column[]':
					case 'table[]':
						$subType = substr($type, 0, -2);
						foreach ($value as &$subValue) {
							$subValue = $this->processModifier($subType, $subValue);
						}
						return implode(', ', $value);

					case 'and':
					case 'or':
						return $this->processWhere($type, $value);

					case 'multiOr':
						return $this->processMultiColumnOr($value);

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

		if (isset($this->customModifiers[$type])) {
			return $this->customModifiers[$type]($value, $type);
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

		} elseif ($value === null && !$typeNullable && $this->modifiers[$baseType][0]) {
			$this->throwWrongModifierException($type, $value, "?$type");

		} elseif (is_array($value) && !$typeArray && $this->modifiers[$baseType][1]) {
			$this->throwWrongModifierException($type, $value, "{$type}[]");

		} else {
			$this->throwInvalidValueTypeException($type, $value, $this->modifiers[$baseType][2]);
		}
	}


	/**
	 * @param  mixed $value
	 * @return void
	 */
	protected function throwInvalidValueTypeException(string $type, $value, string $expectedType)
	{
		$actualType = $this->getVariableTypeName($value);
		throw new InvalidArgumentException("Modifier %$type expects value to be $expectedType, $actualType given.");
	}


	/**
	 * @param  mixed $value
	 * @return void
	 */
	protected function throwWrongModifierException(string $type, $value, string $hint)
	{
		$valueLabel = is_scalar($value) ? var_export($value, true) : gettype($value);
		throw new InvalidArgumentException("Modifier %$type does not allow $valueLabel value, use modifier %$hint instead.");
	}


	protected function processArray(string $type, array $value): string
	{
		$subType = substr($type, 0, -2);
		foreach ($value as &$subValue) {
			$subValue = $this->processModifier($subType, $subValue);
		}

		return '(' . implode(', ', $value) . ')';
	}


	protected function processSet(string $type, array $value): string
	{
		$values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$column = $this->identifierToSql($key[0]);
			$expr = $this->processModifier(isset($key[1]) ? $key[1] : 'any', $val);
			$values[] = "$column = $expr";
		}

		return implode(', ', $values);
	}


	protected function processMultiValues(string $type, array $value): string
	{
		if (empty($value)) {
			throw new InvalidArgumentException('Modifier %values[] must contain at least one array element.');
		}

		$keys = $values = [];
		foreach (array_keys(reset($value)) as $key) {
			$keys[] = $this->identifierToSql(explode('%', (string) $key, 2)[0]);
		}
		foreach ($value as $subValue) {
			if (empty($subValue)) {
				$values[] = '(' . str_repeat('DEFAULT, ', max(count($keys) - 1, 0)) . 'DEFAULT)';
			} else {
				$subValues = [];
				foreach ($subValue as $_key => $val) {
					$key = explode('%', $_key, 2);
					$subValues[] = $this->processModifier(isset($key[1]) ? $key[1] : 'any', $val);
				}
				$values[] = '(' . implode(', ', $subValues) . ')';
			}
		}

		return (!empty($keys) ? '(' . implode(', ', $keys) . ') ' : '') . 'VALUES ' . implode(', ', $values);
	}


	private function processValues(string $type, array $value): string
	{
		if (empty($value)) {
			return 'VALUES (DEFAULT)';
		}

		$keys = $values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', (string) $_key, 2);
			$keys[] = $this->identifierToSql($key[0]);
			$values[] = $this->processModifier(isset($key[1]) ? $key[1] : 'any', $val);
		}

		return '(' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
	}


	private function processWhere(string $type, array $value): string
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
				$column = $this->identifierToSql($key[0]);
				$subType = isset($key[1]) ? $key[1] : 'any';

				if ($subValue === null) {
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


	private function processMultiColumnOr(array $values): string
	{
		if ($this->platform->isSupported(IPlatform::SUPPORT_MULTI_COLUMN_IN)) {
			$keys = [];
			foreach (array_keys(reset($values)) as $key) {
				$keys[] = $this->identifierToSql(explode('%', (string) $key, 2)[0]);
			}
			return '(' . implode(', ', $keys) . ') IN ' . $this->processModifier('any', $values);

		} else {
			$sqls = [];
			foreach ($values as $value) {
				$sqls[] = $this->processWhere('and', $value);
			}
			return '(' . implode(') OR (', $sqls)  . ')';
		}
	}


	/**
	 * @param  mixed $value
	 * @return float|string
	 */
	protected function getVariableTypeName($value)
	{
		return is_object($value) ? get_class($value) : (is_float($value) && !is_finite($value) ? $value : gettype($value));
	}


	protected function identifierToSql(string $key): string
	{
		return $this->identifiers[$key] ?? ($this->identifiers[$key] = $this->driver->convertIdentifierToSql($key)); // = intentionally
	}
}
