<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\Utils\Typos;


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
	 * @return mixed
	 */
	public function __get($name)
	{
		$closest = Typos::getClosest($name, array_keys($this->toArray()), 3);
		throw new InvalidArgumentException("Column '$name' does not exist" . ($closest ? ", did you mean '$closest'?" :  "."));
	}

}
