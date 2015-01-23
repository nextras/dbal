<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace  Nextras\Dbal;

use Nette\Utils\Strings;
use Nextras\Dbal\Drivers\IDriverProvider;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exceptions\InvalidArgumentException;


class SqlProcessor
{
	/** @var IDriverProvider */
	private $driver;

	/** @var string */
	private $pattern;


	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
		$this->pattern = $this->buildPattern();
	}


	public function process($sql, $args)
	{
		return Strings::replace($sql, $this->pattern, function($matches) use (& $args) {
			if (!isset($matches['m'])) {
				return $matches[0];
			}

			return $this->processValue(array_shift($args), substr($matches['m'], 1));
		});
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

		} elseif (isset($type[$len-1]) && $type[$len-1] === '?' && $value === NULL) {
			return 'NULL';

		} elseif ($type === 'table' || $type === 'column') {
			return $this->driver->convertToSql($value, IDriver::TYPE_IDENTIFIER);

		} elseif ($type[0] === 's') {
			return $this->driver->convertToSql($value, IDriver::TYPE_STRING);

		} elseif ($type[0] === 'b') {
			return $this->driver->convertToSql($value, IDriver::TYPE_BOOL);

		} elseif ($type[0] === 'i') {
			return (string) (int) $value;

		} elseif ($type[0] === 'f') {
			return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

		} else {
			throw new InvalidArgumentException("Unknown modifier '{$type}'.");
		}
	}


	private function buildPattern()
	{
		$pattern = '#(?:';
		$pattern .= " ({$this->driver->getTokenRegexp()}) | ";
		return $pattern . ' (?P<m>%\w+\??(?:\[\])?) )#xs';
	}

}
