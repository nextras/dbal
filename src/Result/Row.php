<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use ArrayIterator;
use IteratorAggregate;
use Nextras\Dbal\Exceptions\NotSupportedException;


final class Row implements IteratorAggregate, IRow
{
	/** @var array */
	private $data;


	public function __construct(array $data)
	{
		$this->data = $data;
	}


	public function __get($name)
	{
		if (!array_key_exists($name, $this->data)) {
			throw new \mysqli_sql_exception();
		}

		return $this->data[$name];
	}


	public function __isset($name)
	{
		return array_key_exists($name, $this->data);
	}


	public function __set($name, $value)
	{
		throw new NotSupportedException('Row is read-only.');
	}


	public function __unset($name)
	{
		throw new NotSupportedException('Row is read-only.');
	}


	public function getIterator()
	{
		return new ArrayIterator($this->data);
	}


	public function __debugInfo()
	{
		return $this->data;
	}

}
