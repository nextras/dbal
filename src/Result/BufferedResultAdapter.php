<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use ArrayIterator;
use Nextras\Dbal\Exception\InvalidArgumentException;
use OutOfBoundsException;


/**
 * @internal
 */
class BufferedResultAdapter implements IResultAdapter
{
	/** @var ArrayIterator<array-key, mixed>|null */
	protected ?ArrayIterator $data = null;


	public function __construct(
		protected readonly IResultAdapter $adapter,
	)
	{
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
		$data = $this->getData();

		if ($index === 0) {
			$data->rewind();
			return;
		}

		try {
			$data->seek($index);
		} catch (OutOfBoundsException $e) {
			throw new InvalidArgumentException("Unable to seek in row set to {$index} index.", 0, $e);
		}
	}


	public function fetch(): ?array
	{
		$data = $this->getData();
		$fetched = $data->valid() ? $data->current() : null;
		$data->next();
		return $fetched;
	}


	public function getRowsCount(): int
	{
		return $this->getData()->count();
	}


	public function getTypes(): array
	{
		return $this->adapter->getTypes();
	}


	public function getNormalizers(): array
	{
		return $this->adapter->getNormalizers();
	}


	/**
	 * @return ArrayIterator<array-key, mixed>
	 */
	protected function getData(): ArrayIterator
	{
		if ($this->data === null) {
			$this->data = $this->fetchData();
		}
		return $this->data;
	}


	/**
	 * @return ArrayIterator<array-key, mixed>
	 */
	protected function fetchData(): ArrayIterator
	{
		$rows = [];
		while (($row = $this->adapter->fetch()) !== null) {
			$rows[] = $row;
		}
		return new ArrayIterator($rows);
	}
}
