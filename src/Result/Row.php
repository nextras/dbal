<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Utils\Typos;


class Row extends \stdClass
{
	/**
	 * @phpstan-param array<string, mixed> $data
	 */
	public function __construct(array $data)
	{
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}


	/**
	 * @phpstan-return array<string, mixed>
	 */
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
		throw new InvalidArgumentException("Column '$name' does not exist" . ($closest !== null ? ", did you mean '$closest'?" : "."));
	}


	/**
	 * @return mixed
	 */
	public function getNthField(int $offset)
	{
		$slice = array_slice((array) $this, $offset, 1);
		if (count($slice) === 0) {
			throw new InvalidArgumentException("Column '$offset' does not exist.");
		}
		return current($slice);
	}
}
