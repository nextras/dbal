<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Iterator;


class RowsetIterator implements Iterator
{
	/** @var IRowset */
	private $rowset;

	/** @var IRow */
	private $row;

	/** @var int */
	private $index;


	public function __construct(IRowset $result)
	{
		$this->rowset = $result;
	}


	public function rewind()
	{
		$this->index = 0;
		$this->rowset->seek(0);
		$this->row = $this->rowset->fetch();
	}


	public function key()
	{
		return $this->index;
	}


	public function current()
	{
		return $this->row;
	}


	public function next()
	{
		$this->index += 1;
		$this->row = $this->rowset->fetch();
	}


	public function valid()
	{
		return !empty($this->row);
	}

}
