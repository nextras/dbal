<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use ArrayIterator;


/**
 * @internal
 */
class FullyBufferedResultAdapter extends BufferedResultAdapter
{
	/** @var array<string, array{int, mixed}>|null */
	protected $types = null;


	public function getTypes(): array
	{
		$this->getData();
		assert($this->types !== null);
		return $this->types;
	}


	public function getRowsCount(): int
	{
		return $this->getData()->count();
	}


	protected function fetchData(): ArrayIterator
	{
		$rows = [];
		while (($row = $this->adapter->fetch()) !== null) {
			if ($this->types === null) {
				$this->types = $this->adapter->getTypes();
			}
			$rows[] = $row;
		}
		return new ArrayIterator($rows);
	}
}
