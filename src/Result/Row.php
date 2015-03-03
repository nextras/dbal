<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Nextras\Dbal\Exceptions\InvalidArgumentException;


class Row
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
	 */
	public function __get($name)
	{
		throw new InvalidArgumentException("Column '$name' does not exist.");
	}

}
