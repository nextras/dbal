<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use IteratorAggregate;
use Nextras\Dbal\Drivers\IRowsetAdapter;
use Nextras\Dbal\Exceptions\DbalException;


class Rowset implements IteratorAggregate, IRowset
{
	/** @var IRowsetAdapter */
	private $adapter;


	public function __construct(IRowsetAdapter $adapter)
	{
		$this->adapter = $adapter;
	}


	public function seek($index)
	{
		if (!$this->adapter->seek($index)) {
			throw new DbalException("Unable to seek in row set to {$index} index.");
		}
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


	public function getIterator()
	{
		return new RowsetIterator($this);
	}

}
