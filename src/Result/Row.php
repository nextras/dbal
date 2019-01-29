<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\Utils\Typos;


class Row extends \stdClass
{
	public function __construct(array $data)
	{
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}


	public function toArray(): array
	{
		return (array) $this;
	}


	/**
	 * @return mixed
	 */
	public function __get(string $name)
	{
		$closest = Typos::getClosest($name, array_keys($this->toArray()));
		throw new InvalidArgumentException("Column '$name' does not exist" . ($closest ? ", did you mean '$closest'?" :  "."));
	}


	/**
	 * @return mixed
	 */
	public function getNthField(int $offset)
	{
		$slice = array_slice((array) $this, $offset, 1);
		if (!$slice) {
			throw new InvalidArgumentException("Column '$offset' does not exist.");
		}
		return current($slice);
	}
}
