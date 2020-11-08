<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use ArrayIterator;
use Nextras\Dbal\Exception\InvalidArgumentException;
use OutOfBoundsException;
use function assert;


class BufferedResultAdapter implements IResultAdapter
{
	/** @var IResultAdapter */
	private $adapter;

	/** @var ArrayIterator<mixed, mixed>|null */
	private $data;


	public function __construct(IResultAdapter $adapter)
	{
		$this->adapter = $adapter;
	}


	public function toBuffered(): IResultAdapter
	{
		return $this;
	}


	public function toUnbuffered(): IResultAdapter
	{
		if ($this->data === null) {
			return $this->adapter->toUnbuffered();
		} else {
			return $this;
		}
	}


	public function seek(int $index): void
	{
		if ($this->data === null) {
			$this->init();
		}
		assert($this->data !== null);

		if ($index === 0) {
			$this->data->rewind();
			return;
		}

		try {
			$this->data->seek($index);
		} catch (OutOfBoundsException $e) {
			throw new InvalidArgumentException("Unable to seek in row set to {$index} index.", 0, $e);
		}
	}


	public function fetch(): ?array
	{
		if ($this->data === null) {
			$this->init();
		}
		assert($this->data !== null);

		$fetched = $this->data->valid() ? $this->data->current() : null;
		$this->data->next();
		return $fetched;
	}


	public function getTypes(): array
	{
		return $this->adapter->getTypes();
	}


	public function getRowsCount(): int
	{
		if ($this->data === null) {
			$this->init();
		}
		assert($this->data !== null);

		return $this->data->count();
	}


	private function init(): void
	{
		$rows = [];
		while (($row = $this->adapter->fetch()) !== null) {
			$rows[] = $row;
		}
		$this->data = new ArrayIterator($rows);
	}
}
