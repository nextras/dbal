<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoPgsql;


use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\Utils\StrictObjectTrait;
use PDO;
use PDOStatement;
use function assert;


class PdoPgsqlResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	/** @var array<string, int> */
	protected static $types = [
		'bit' => self::TYPE_DRIVER_SPECIFIC,
		'varbit' => self::TYPE_DRIVER_SPECIFIC,
		'bytea' => self::TYPE_DRIVER_SPECIFIC,
		'interval' => self::TYPE_DRIVER_SPECIFIC,

		'int8' => self::TYPE_INT,
		'int4' => self::TYPE_INT,
		'int2' => self::TYPE_INT,

		'numeric' => self::TYPE_FLOAT,
		'float4' => self::TYPE_FLOAT,
		'float8' => self::TYPE_FLOAT,

		'time' => self::TYPE_DATETIME,
		'date' => self::TYPE_DATETIME,
		'timestamp' => self::TYPE_DATETIME,
		'timetz' => self::TYPE_DATETIME,
		'timestamptz' => self::TYPE_DATETIME,
	];

	/** @var PDOStatement<mixed> */
	protected $statement;

	/** @var bool */
	protected $beforeFirstFetch = true;


	/**
	 * @param PDOStatement<mixed> $statement
	 */
	public function __construct(PDOStatement $statement)
	{
		$this->statement = $statement;
	}


	public function seek(int $index): void
	{
		if ($index === 0 && $this->beforeFirstFetch) return;
		throw new NotSupportedException("PDO does not support seek & replay. Use Result::fetchAll() to and result its result.");
	}


	public function fetch(): ?array
	{
		if ($this->beforeFirstFetch && $this->statement->columnCount() === 0) {
			$this->beforeFirstFetch = false;
			return null;
		}

		$this->beforeFirstFetch = false;
		$fetched = $this->statement->fetch(PDO::FETCH_ASSOC);
		return $fetched !== false ? $fetched : null;
	}


	public function getTypes(): array
	{
		$types = [];
		$count = $this->statement->columnCount();

		for ($i = 0; $i < $count; $i++) {
			$field = $this->statement->getColumnMeta($i);
			if ($field === false) { // @phpstan-ignore-line
				throw new InvalidStateException("Should not happen.");
			}
			$types[(string) $field['name']] = [
				0 => self::$types[$field['native_type']] ?? self::TYPE_AS_IS,
				1 => $field['native_type'],
			];
		}

		return $types;
	}


	public function getRowsCount(): int
	{
		return $this->statement->rowCount();
	}
}
