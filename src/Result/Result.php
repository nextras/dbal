<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\IResultAdapter;


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
	private $toIntColumns = [];

	/** @var string[] list of columns which should be casted to float */
	private $toFloatColumns = [];

	/** @var string[] list of columns which should be casted to string */
	private $toStringColumns = [];

	/** @var string[] list of columns which should be casted to bool */
	private $toBoolColumns = [];

	/** @var string[] list of columns which should be casted to DateTime */
	private $toDateTimeColumns = [];

	/** @var array[] list of columns which should be casted using driver-specific logic */
	private $toDriverColumns = [];

	/** @var array[] list of columns which should be casted using callback */
	private $toCallbackColumns = [];


	public function __construct(IResultAdapter $adapter, IDriver $driver)
	{
		$this->adapter = $adapter;
		$this->driver = $driver;
		$this->initColumnConversions();
	}


	/**
	 * @return IResultAdapter
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}


	/**
	 * Enabled and disabled column value normalization.
	 * @param  bool $enabled
	 */
	public function setColumnValueNormalization($enabled = FALSE)
	{
		$this->toIntColumns = [];
		$this->toFloatColumns = [];
		$this->toStringColumns = [];
		$this->toBoolColumns = [];
		$this->toDateTimeColumns = [];
		$this->toDriverColumns = [];
		$this->toCallbackColumns = [];
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


	/** @return mixed|NULL */
	public function fetchField()
	{
		if ($row = $this->fetch()) { // = intentionally
			foreach ($row as $value) {
				return $value;
			}
		}

		return NULL;
	}


	protected function initColumnConversions()
	{
		$types = $this->adapter->getTypes();
		foreach ($types as $key => $typePair) {
			list($type, $nativeType) = $typePair;
			if ($type === IResultAdapter::TYPE_STRING) {
				$this->toStringColumns[] = $key;

			} elseif ($type === IResultAdapter::TYPE_INT) {
				$this->toIntColumns[] = $key;

			} elseif ($type === IResultAdapter::TYPE_DRIVER_SPECIFIC) {
				$this->toDriverColumns[] = [$key, $nativeType];

			} elseif ($type === IResultAdapter::TYPE_FLOAT) {
				$this->toFloatColumns[] = $key;

			} elseif ($type === IResultAdapter::TYPE_BOOL) {
				$this->toBoolColumns[] = $key;

			} elseif ($type === IResultAdapter::TYPE_DATETIME) {
				$this->toDateTimeColumns[] = $key;

			} elseif (is_callable($type)) {
				$this->toCallbackColumns[] = [$key, $type];
			}
		}
	}


	protected function normalize($data)
	{
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
				$data[$column] = new \DateTime($data[$column]);
			}
		}

		foreach ($this->toDriverColumns as $meta) {
			list($column, $nativeType) = $meta;
			if ($data[$column] !== NULL) {
				$data[$column] = $this->driver->convertToPhp($data[$column], $nativeType);
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
	}


	public function seek($index)
	{
		$this->adapter->seek($index);
		$this->iteratorIndex = $index - 1;
		$this->fetch();
	}
}
