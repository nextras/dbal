<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use ArrayAccess;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\NotSupportedException;
use Nextras\Dbal\Utils\Typos;


class Row implements ArrayAccess
{
	/**
	 * @param  array $data
	 */
	public function __construct(array $data)
	{
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}


	/**
	 * @return array
	 */
	public function toArray()
	{
		return (array) $this;
	}


	/**
	 * @param  string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		$closest = Typos::getClosest($name, array_keys($this->toArray()), 3);
		throw new InvalidArgumentException("Column '$name' does not exist" . ($closest ? ", did you mean '$closest'?" :  "."));
	}


	public function offsetExists($offset)
	{
		if (!is_int($offset)) {
			throw new NotSupportedException('Array access is suported only for indexed reading. Use property access.');
		}

		return $offset >= 0 && $offset < count((array) $this);
	}


	public function offsetGet($offset)
	{
		if (!is_int($offset)) {
			throw new NotSupportedException('Array access is suported only for indexed reading. Use property access.');
		}

		$slice = array_slice((array) $this, $offset, 1);
		if (!$slice) {
			throw new InvalidArgumentException("Column '$offset' does not exist.");
		}
		return current($slice);
	}


	public function offsetSet($offset, $value)
	{
		throw new NotSupportedException('Array access is suported only for indexed reading. Use property access.');
	}


	public function offsetUnset($offset)
	{
		throw new NotSupportedException('Array access is suported only for indexed reading. Use property access.');
	}
}
