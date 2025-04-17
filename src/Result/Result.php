<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use Countable;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Dbal\Utils\StrictObjectTrait;
use SeekableIterator;
use function array_keys;
use function array_map;
use function assert;
use function iterator_to_array;


/**
 * @implements SeekableIterator<int, Row>
 */
class Result implements SeekableIterator, Countable
{
	use StrictObjectTrait;


	private int $iteratorIndex = -1;

	private ?Row $iteratorRow = null;

	/** @var array<string, callable(mixed): mixed> */
	private array $normalizers;


	public function __construct(private IResultAdapter $adapter)
	{
		$this->normalizers = $adapter->getNormalizers();
	}


	/**
	 * Enables emulated buffering mode to allow rewinding the result multiple times or seeking
	 * to a specific position. This will enable emulated buffering for drivers that do not support
	 * buffering & scrolling the result.
	 */
	public function buffered(): Result
	{
		$this->adapter = $this->adapter->toBuffered();
		return $this;
	}


	/**
	 * Disables emulated buffering mode. Emulated buffering may not be disabled when the result was
	 * already (partially) consumed.
	 */
	public function unbuffered(): Result
	{
		$this->adapter = $this->adapter->toUnbuffered();
		return $this;
	}


	public function getAdapter(): IResultAdapter
	{
		return $this->adapter;
	}


	public function fetch(): ?Row
	{
		$data = $this->adapter->fetch();
		if ($data === null) {
			$row = null;
		} else {
			$this->normalize($data);
			$row = new Row($data);
		}
		$this->iteratorIndex++;
		return $this->iteratorRow = $row;
	}


	/**
	 * Enables and disables value normalization.
	 * Disabling removes all normalizers, enabling resets the default driver's normalizers.
	 */
	public function setValueNormalization(bool $enabled = false): void
	{
		if ($enabled === true) {
			$this->normalizers = $this->adapter->getNormalizers();
		} else {
			$this->normalizers = [];
		}
	}


	/**
	 * @param array<mixed> $data
	 */
	private function normalize(array &$data): void
	{
		foreach ($this->normalizers as $column => $normalizer) {
			if (!isset($data[$column]) && !array_key_exists($column, $data)) {
				continue;
			}

			$data[$column] = $normalizer($data[$column]);
		}
	}


	/**
	 * @return mixed|null
	 */
	public function fetchField(int $column = 0)
	{
		if (($row = $this->fetch()) !== null) { // = intentionally
			return $row->getNthField($column);
		}

		return null;
	}


	/**
	 * @return Row[]
	 */
	public function fetchAll(): array
	{
		return iterator_to_array($this);
	}


	/**
	 * @return array<mixed>
	 */
	public function fetchPairs(?string $key = null, ?string $value = null): array
	{
		if ($key === null && $value === null) {
			throw new InvalidArgumentException('Result::fetchPairs() requires defined key or value.');
		}

		$return = [];
		$this->seek(0);

		if ($key === null) {
			while (($row = $this->fetch()) !== null) {
				$return[] = $row->{$value};
			}
		} elseif ($value === null) {
			while (($row = $this->fetch()) !== null) {
				$return[($row->{$key} instanceof DateTimeImmutable) ? (string) $row->{$key} : $row->{$key}] = $row;
			}
		} else {
			while (($row = $this->fetch()) !== null) {
				$return[($row->{$key} instanceof DateTimeImmutable) ? (string) $row->{$key} : $row->{$key}] = $row->{$value};
			}
		}

		return $return;
	}


	/**
	 * Returns list of column names in result.
	 * @return list<string>
	 */
	public function getColumns(): array
	{
		return array_map(
			function($name): string {
				return (string) $name; // @phpstan-ignore-line
			},
			array_keys($this->adapter->getTypes()),
		);
	}


	public function key(): int
	{
		return $this->iteratorIndex;
	}


	public function current(): Row
	{
		assert($this->iteratorRow !== null);
		return $this->iteratorRow;
	}


	public function next(): void
	{
		$this->fetch();
	}


	public function valid(): bool
	{
		return $this->iteratorRow !== null;
	}


	public function rewind(): void
	{
		$this->seek(0);
		$this->fetch();
	}


	/**
	 * @param int $offset
	 */
	public function seek($offset): void
	{
		$this->adapter->seek($offset);
		$this->iteratorIndex = $offset - 1;
	}


	public function count(): int
	{
		return $this->adapter->getRowsCount();
	}
}
