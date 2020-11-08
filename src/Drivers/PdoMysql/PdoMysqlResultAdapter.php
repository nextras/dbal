<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoMysql;


use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Utils\StrictObjectTrait;
use PDO;
use PDOStatement;


class PdoMysqlResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	/** @var array<string, int> */
	protected static $types = [
		'TIME' => self::TYPE_DRIVER_SPECIFIC,
		'DATE' => self::TYPE_DATETIME,
		'DATETIME' => self::TYPE_DATETIME,
		'TIMESTAMP' => self::TYPE_DRIVER_SPECIFIC | self::TYPE_DATETIME,

		'BIT' => self::TYPE_INT,
		'INT24' => self::TYPE_INT,
		'INTERVAL' => self::TYPE_INT,
		'TINY' => self::TYPE_INT,
		'SHORT' => self::TYPE_INT,
		'LONG' => self::TYPE_INT,
		'LONGLONG' => self::TYPE_INT,
		'YEAR' => self::TYPE_INT,

		'DECIMAL' => self::TYPE_FLOAT,
		'NEWDECIMAL' => self::TYPE_FLOAT,
		'DOUBLE' => self::TYPE_FLOAT,
		'FLOAT' => self::TYPE_FLOAT,

		'VAR_STRING' => self::TYPE_AS_IS,
		'STRING' => self::TYPE_AS_IS,
		'BLOB' => self::TYPE_AS_IS,
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
