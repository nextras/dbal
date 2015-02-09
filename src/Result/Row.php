<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Nextras\Dbal\Exceptions\InvalidArgumentException;
use Nextras\Dbal\Exceptions\NotSupportedException;


final class Row implements IteratorAggregate, ArrayAccess
{
	/** @var array */
	private $data;


	public function __construct(array $data)
	{
		$this->data = $data;
	}


	public function __get($name)
	{
		if (!(isset($this->data[$name]) || array_key_exists($name, $this->data))) {
			throw new InvalidArgumentException("Undefined property '{$name}'.");
		}

		return $this->data[$name];
	}


	public function __isset($name)
	{
		return isset($this->data[$name]) || array_key_exists($name, $this->data);
	}


	public function __set($name, $value)
	{
		throw new NotSupportedException('Row is read-only.');
	}


	public function __unset($name)
	{
		throw new NotSupportedException('Row is read-only.');
	}


	public function offsetExists($offset)
	{
		if (is_int($offset)) {
			return count($this->data) > $offset;
		}

		return isset($this->$offset);
	}


	public function offsetGet($offset)
	{
		if (is_int($offset)) {
			$array = array_slice($this->data, $offset, 1);
			if (!$array) {
				throw new InvalidArgumentException("Undefined offset '{$offset}'.");
			}

			return current($array);
		}

		return $this->$offset;
	}


	public function offsetSet($offset, $value)
	{
		throw new NotSupportedException('Row is read-only.');
	}


	public function offsetUnset($offset)
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
