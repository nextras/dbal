<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use DateTimeZone;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\Utils\DateTime;


class Result implements \SeekableIterator
{
	/** @var IResultAdapter */
	private $adapter;

	/** @var int */
	private $iteratorIndex;

	/** @var Row|NULL */
	private $iteratorRow;

	/** @var IDriver */
	private $driver;

	/** @var string[] list of columns which should be casted to int */
	private $toIntColumns;

	/** @var string[] list of columns which should be casted to float */
	private $toFloatColumns;

	/** @var string[] list of columns which should be casted to string */
	private $toStringColumns;

	/** @var string[] list of columns which should be casted to bool */
	private $toBoolColumns;

	/** @var string[] list of columns which should be casted to DateTime */
	private $toDateTimeColumns;

	/** @var array[] list of columns which should be casted using driver-specific logic */
	private $toDriverColumns;

	/** @var DateTimeZone */
	private $applicationTimeZone;

	/** @var float */
	private $elapsedTime;


	public function __construct(IResultAdapter $adapter, IDriver $driver, $elapsedTime)
	{
		$this->adapter = $adapter;
		$this->driver = $driver;
		$this->applicationTimeZone = new DateTimeZone(date_default_timezone_get());
		$this->initColumnConversions();
		$this->elapsedTime = $elapsedTime;
	}


	/**
	 * @return IResultAdapter
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}


	/**
	 * Enables and disables value normalization.
	 * @param  bool $enabled
	 */
	public function setValueNormalization($enabled = FALSE)
	{
		if ($enabled === TRUE) {
			$this->initColumnConversions();
		} else {
			$this->toIntColumns = [];
			$this->toFloatColumns = [];
			$this->toStringColumns = [];
			$this->toBoolColumns = [];
			$this->toDateTimeColumns = [];
			$this->toDriverColumns = [];
		}
	}


	/**
	 * @return Row|NULL
	 */
	public function fetch()
	{
		$data = $this->adapter->fetch();
		$row = ($data === NULL ? NULL : new Row($this->normalize($data)));
		$this->iteratorIndex++;
		return $this->iteratorRow = $row;
	}


	/**
	 * @param  int $column
	 * @return mixed|NULL
	 */
	public function fetchField($column = 0)
	{
		if ($row = $this->fetch()) { // = intentionally
			return $row[$column];
		}

		return NULL;
	}


	/**
	 * @return Row[]
	 */
	public function fetchAll()
	{
		return iterator_to_array($this);
	}


	/**
	 * @param  string|NULL $key
	 * @param  string|NULL $value
	 * @return array
	 */
	public function fetchPairs($key = NULL, $value = NULL)
	{
		if ($key === NULL && $value === NULL) {
			throw new InvalidArgumentException('Result::fetchPairs() requires defined key or value.');
		}

		$return = [];
		$this->seek(0);

		if ($key === NULL) {
			while ($row = $this->fetch()) {
				$return[] = $row->{$value};
			}
		} elseif ($value === NULL) {
			while ($row = $this->fetch()) {
				$return[is_object($row->{$key}) ? (string) $row->{$key} : $row->{$key}] = $row;
			}
		} else {
			while ($row = $this->fetch()) {
				$return[is_object($row->{$key}) ? (string) $row->{$key} : $row->{$key}] = $row->{$value};
			}
		}

		return $return;
	}


	/**
	 * @return float
	 */
	public function getElapsedTime()
	{
		return $this->elapsedTime;
	}


	protected function initColumnConversions()
	{
		$this->toIntColumns = [];
		$this->toFloatColumns = [];
		$this->toStringColumns = [];
		$this->toBoolColumns = [];
		$this->toDateTimeColumns = [];
		$this->toDriverColumns = [];

		$types = $this->adapter->getTypes();
		foreach ($types as $key => $typePair) {
			list($type, $nativeType) = $typePair;

			if ($type & IResultAdapter::TYPE_STRING) {
				$this->toStringColumns[] = $key;

			} elseif ($type & IResultAdapter::TYPE_INT) {
				$this->toIntColumns[] = $key;

			} elseif ($type & IResultAdapter::TYPE_FLOAT) {
				$this->toFloatColumns[] = $key;

			} elseif ($type & IResultAdapter::TYPE_BOOL) {
				$this->toBoolColumns[] = $key;

			} elseif ($type & IResultAdapter::TYPE_DATETIME) {
				$this->toDateTimeColumns[] = $key;
			}

			if ($type & IResultAdapter::TYPE_DRIVER_SPECIFIC) {
				$this->toDriverColumns[] = [$key, $nativeType];
			}
		}
	}


	protected function normalize($data)
	{
		foreach ($this->toDriverColumns as $meta) {
			list($column, $nativeType) = $meta;
			if ($data[$column] !== NULL) {
				$data[$column] = $this->driver->convertToPhp($data[$column], $nativeType);
			}
		}

		foreach ($this->toIntColumns as $column) {
			if ($data[$column] !== NULL) {
				$data[$column] = (int) $data[$column];
			}
		}

		foreach ($this->toFloatColumns as $column) {
			if ($data[$column] !== NULL) {
				$data[$column] = (float) $data[$column];
			}
		}

		foreach ($this->toBoolColumns as $column) {
			if ($data[$column] !== NULL) {
				$data[$column] = (bool) $data[$column];
			}
		}

		foreach ($this->toStringColumns as $column) {
			if ($data[$column] !== NULL) {
				$data[$column] = (string) $data[$column];
			}
		}

		foreach ($this->toDateTimeColumns as $column) {
			if ($data[$column] !== NULL) {
				$data[$column] = (new DateTime($data[$column]))->setTimezone($this->applicationTimeZone);
			}
		}

		return $data;
	}


	// === SeekableIterator ============================================================================================


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
		$this->fetch();
	}


	public function valid()
	{
		return $this->iteratorRow !== NULL;
	}


	public function rewind()
	{
		$this->seek(0);
		$this->fetch();
	}


	public function seek($index)
	{
		$this->adapter->seek($index);
		$this->iteratorIndex = $index - 1;
	}
}
