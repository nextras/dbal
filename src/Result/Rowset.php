<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Iterator;
use Nextras\Dbal\Drivers\IRowsetAdapter;


class Rowset implements Iterator, IRowset
{
	/** @var IRowsetAdapter */
	private $adapter;

	/** @var int */
	private $iteratorIndex;

	/** @var IRow */
	private $iteratorRow;


	public function __construct(IRowsetAdapter $adapter)
	{
		$this->adapter = $adapter;
	}


	/**
	 * @return IRow
	 */
	public function fetch()
	{
		$data = $this->adapter->fetch();
		if ($data === NULL) {
			return NULL;
		}

		return new Row($data);
	}


	// === iterator ====================================================================================================


	public function rewind()
	{
		$this->iteratorIndex = 0;
		$this->adapter->seek(0);
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
		$this->iteratorIndex += 1;
		$this->iteratorRow = $this->fetch();
	}


	public function valid()
	{
		return !empty($this->iteratorRow);
	}


}
