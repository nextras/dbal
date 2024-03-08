<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Utils\Typos;


class Row extends \stdClass
{
	/**
	 * @param array<string, mixed> $data
	 */
	public function __construct(array $data)
	{
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}


	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return (array) $this;
	}


	public function __get(string $name): mixed
	{
		$closest = Typos::getClosest($name, array_keys($this->toArray()));
		throw new InvalidArgumentException("Column '$name' does not exist" . ($closest !== null ? ", did you mean '$closest'?" : "."));
	}


	public function getNthField(int $offset): mixed
	{
		$slice = array_slice((array) $this, $offset, 1);
		if (count($slice) === 0) {
			throw new InvalidArgumentException("Column '$offset' does not exist.");
		}
		return current($slice);
	}
}
