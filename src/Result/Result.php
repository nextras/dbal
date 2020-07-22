<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use Countable;
use DateTimeZone;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Dbal\Utils\StrictObjectTrait;
use SeekableIterator;
use function assert;
use function date_default_timezone_get;
use function iterator_to_array;


/**
 * @implements SeekableIterator<int, Row>
 */
class Result implements SeekableIterator, Countable
{
	use StrictObjectTrait;


	/** @var IResultAdapter */
	private $adapter;

	/** @var int */
	private $iteratorIndex;

	/** @var Row|null */
	private $iteratorRow;

	/** @var IDriver */
	private $driver;

	/** @var string[] list of columns which should be casted to int */
	private $toIntColumns;

	/** @var string[] list of columns which should be casted to float */
	private $toFloatColumns;

	/** @var string[] list of columns which should be casted to string */
	private $toStringColumns;

	/** @var string[] list of columns which should be casted to bool */
	private $toBoolColumns;

	/** @var string[] list of columns which should be casted to DateTime */
	private $toDateTimeColumns;

	/**
	 * @var array[] list of columns which should be casted using driver-specific logic
	 * @phpstan-var array<array{string, int}>
	 */
	private $toDriverColumns;

	/** @var DateTimeZone */
	private $applicationTimeZone;


	public function __construct(IResultAdapter $adapter, IDriver $driver)
	{
		$this->adapter = $adapter;
		$this->driver = $driver;
		$this->applicationTimeZone = new DateTimeZone(date_default_timezone_get());
		$this->initColumnConversions();
	}


	public function getAdapter(): IResultAdapter
	{
		return $this->adapter;
	}


	/**
	 * Enables and disables value normalization.
	 */
	public function setValueNormalization(bool $enabled = false): void
	{
		if ($enabled === true) {
			$this->initColumnConversions();
		} else {
			$this->toIntColumns = [];
			$this->toFloatColumns = [];
			$this->toStringColumns = [];
			$this->toBoolColumns = [];
			$this->toDateTimeColumns = [];
			$this->toDriverColumns = [];
		}
	}


	public function fetch(): ?Row
	{
		$data = $this->adapter->fetch();
		$row = ($data === null ? null : new Row($this->normalize($data)));
		$this->iteratorIndex++;
		return $this->iteratorRow = $row;
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
	 * @phpstan-return array<mixed>
	 */
	public function fetchPairs(?string $key = null, ?string $value = null): array
	{
		if ($key === null && $value === null) {
			throw new InvalidArgumentException('Result::fetchPairs() requires defined key or value.');
		}

		$return = [];
		$this->seek(0);

		if ($key === null) {
			while ($row = $this->fetch()) {
				$return[] = $row->{$value};
			}
		} elseif ($value === null) {
			while ($row = $this->fetch()) {
				$return[($row->{$key} instanceof DateTimeImmutable) ? (string) $row->{$key} : $row->{$key}] = $row;
			}
		} else {
			while ($row = $this->fetch()) {
				$return[($row->{$key} instanceof DateTimeImmutable) ? (string) $row->{$key} : $row->{$key}] = $row->{$value};
			}
		}

		return $return;
	}


	protected function initColumnConversions(): void
	{
		$this->toIntColumns = [];
		$this->toFloatColumns = [];
		$this->toStringColumns = [];
		$this->toBoolColumns = [];
		$this->toDateTimeColumns = [];
		$this->toDriverColumns = [];

		$types = $this->adapter->getTypes();
		foreach ($types as $key => $typePair) {
			[$type, $nativeType] = $typePair;

			if (($type & IResultAdapter::TYPE_STRING) > 0) {
				$this->toStringColumns[] = $key;

			} elseif (($type & IResultAdapter::TYPE_INT) > 0) {
				$this->toIntColumns[] = $key;

			} elseif (($type & IResultAdapter::TYPE_FLOAT) > 0) {
				$this->toFloatColumns[] = $key;

			} elseif (($type & IResultAdapter::TYPE_BOOL) > 0) {
				$this->toBoolColumns[] = $key;

			} elseif (($type & IResultAdapter::TYPE_DATETIME) > 0) {
				$this->toDateTimeColumns[] = $key;
			}

			if (($type & IResultAdapter::TYPE_DRIVER_SPECIFIC) > 0) {
				$this->toDriverColumns[] = [$key, $nativeType];
			}
		}
	}


	/**
	 * @phpstan-param array<string, mixed> $data
	 * @phpstan-return array<string, mixed>
	 */
	protected function normalize(array $data): array
	{
		foreach ($this->toDriverColumns as $meta) {
			[$column, $nativeType] = $meta;
			if ($data[$column] !== null) {
				$data[$column] = $this->driver->convertToPhp($data[$column], $nativeType);
			}
		}

		foreach ($this->toIntColumns as $column) {
			if ($data[$column] !== null) {
				$data[$column] = (int) $data[$column];
			}
		}

		foreach ($this->toFloatColumns as $column) {
			if ($data[$column] !== null) {
				$data[$column] = (float) $data[$column];
			}
		}

		foreach ($this->toBoolColumns as $column) {
			if ($data[$column] !== null) {
				$data[$column] = (bool) $data[$column];
			}
		}

		foreach ($this->toStringColumns as $column) {
			if ($data[$column] !== null) {
				$data[$column] = (string) $data[$column];
			}
		}

		foreach ($this->toDateTimeColumns as $column) {
			if ($data[$column] !== null) {
				$data[$column] = (new DateTimeImmutable($data[$column]))->setTimezone($this->applicationTimeZone);
			}
		}

		return $data;
	}


	// === SeekableIterator ============================================================================================

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
	 * @param int $index
	 */
	public function seek($index): void
	{
		$this->adapter->seek($index);
		$this->iteratorIndex = $index - 1;
	}


	// === Countable ===================================================================================================

	public function count(): int
	{
		return $this->adapter->getRowsCount();
	}
}
