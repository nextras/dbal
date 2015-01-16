<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Iterator;
use Nette\Utils\Callback;
use Nette\Utils\DateTime;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\IRowsetAdapter;
use Nextras\Dbal\Exceptions\InvalidArgumentException;


class Rowset implements Iterator, IRowset
{
	/** @var IRowsetAdapter */
	private $adapter;

	/** @var int */
	private $iteratorIndex;

	/** @var Row */
	private $iteratorRow;

	/** @var NULL|bool|array */
	private $types;

	/** @var IDriver */
	private $driver;


	public function __construct(IRowsetAdapter $adapter, IDriver $driver)
	{
		$this->adapter = $adapter;
		$this->driver = $driver;
	}


	/**
	 * Enabled and disabled column value normalization.
	 * @param  bool $enabled
	 */
	public function setColumnValueNormalization($enabled = FALSE)
	{
		$this->types = $enabled ? NULL : FALSE;
	}


	/**
	 * Sets column type.
	 * @param string $column
	 * @param int    $type
	 * @param mixed  $nativeType
	 */
	public function setColumnType($column, $type, $nativeType = NULL)
	{
		if ($this->types === NULL) {
			$this->types = $this->adapter->getTypes();
		}

		if ($type === IRowsetAdapter::TYPE_DRIVER_SPECIFIC && $nativeType === NULL) {
			throw new InvalidArgumentException('Undefined native type for driver resolution.');
		}

		$this->types[$column] = [$type, $nativeType];
	}


	/**
	 * Returns detected column type.
	 * If column does not exists in resulset, returns NULL.
	 * @param  string $column
	 * @return array|NULL
	 */
	public function getColumnType($column)
	{
		if ($this->types === NULL) {
			$this->types = $this->adapter->getTypes();
		}

		return isset($this->types[$column]) ? $this->types[$column] : NULL;
	}


	/**
	 * @return Row|NULL
	 */
	public function fetch()
	{
		$data = $this->adapter->fetch();
		if ($data === NULL) {
			return NULL;
		}

		if ($this->types !== FALSE) {
			if ($this->types === NULL) {
				$this->types = $this->adapter->getTypes();
			}
			$data = $this->normalize($data);
		}

		return new Row($data);
	}


	protected function normalize($data)
	{
		foreach ($this->types as $key => $typePair) {
			list($type, $nativeType) = $typePair;
			$value = $data[$key];

			if ($value === NULL || $type === IRowsetAdapter::TYPE_STRING) {
				// nothing to do

			} elseif ($type === IRowsetAdapter::TYPE_DRIVER_SPECIFIC) {
				$data[$key] = $this->driver->convertToPhp($value, $nativeType);

			} elseif ($type === IRowsetAdapter::TYPE_INT) {
				// number is to big for integer type
				$data[$key] = is_float($tmp = $value * 1) ? $value : $tmp;

			} elseif ($type === IRowsetAdapter::TYPE_FLOAT) {
				// number is to big for float type
				$pointPos = strpos($value, '.');
				if ($pointPos !== FALSE) {
					$value = rtrim(rtrim($pointPos === 0 ? "0{$value}" : $value, '.'), '0');
				}
				$float = (float) $value;
				$data[$key] = number_format($float, strlen($value) - $pointPos - 1, '.', '') === $value ? $float : $value;

			} elseif ($type === IRowsetAdapter::TYPE_BOOL) {
				$data[$key] = (bool) $value;

			} elseif ($type === IRowsetAdapter::TYPE_DATETIME) {
				$data[$key] = DateTime::from($value);

			} elseif (is_callable($type)) {
				$data[$key] = Callback::invokeArgs($type, [$value]);
			}
		}

		return $data;
	}


	// === iterator ====================================================================================================


	public function rewind()
	{
		$this->adapter->seek(0);
		$this->iteratorIndex = 0;
		$this->iteratorRow = $this->fetch();
	}


	public function key()
	{
		return $this->iteratorIndex;
	}


	public function current()
	{
		return $this->iteratorRow;
	}


	public function next()
	{
		$this->iteratorIndex++;
		$this->iteratorRow = $this->fetch();
	}


	public function valid()
	{
		return $this->iteratorRow !== NULL;
	}

}
