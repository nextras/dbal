<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoSqlsrv;


use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\Result\BufferedResultAdapter;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Utils\StrictObjectTrait;
use PDO;
use PDOStatement;


class PdoSqlsrvResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	/** @var array<string, int> */
	protected static $types = [
		'bit' => self::TYPE_BOOL,

		'bigint' => self::TYPE_INT,
		'int' => self::TYPE_INT,
		'smallint' => self::TYPE_INT,
		'tinyint' => self::TYPE_INT,

		'real' => self::TYPE_FLOAT,
		'numeric' => self::TYPE_DRIVER_SPECIFIC,
		'decimal' => self::TYPE_DRIVER_SPECIFIC,
		'money' => self::TYPE_DRIVER_SPECIFIC,
		'smallmoney' => self::TYPE_DRIVER_SPECIFIC,

		'time' => self::TYPE_DATETIME,
		'date' => self::TYPE_DATETIME,
		'smalldatetime' => self::TYPE_DATETIME,
		'datetimeoffset' => self::TYPE_DRIVER_SPECIFIC,
		'datetime' => self::TYPE_DATETIME,
		'datetime2' => self::TYPE_DATETIME,
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


	public function toBuffered(): IResultAdapter
	{
		return new BufferedResultAdapter($this);
	}


	public function toUnbuffered(): IResultAdapter
	{
		return $this;
	}


	public function seek(int $index): void
	{
		if ($index === 0 && $this->beforeFirstFetch) {
			return;
		}

		throw new NotSupportedException("PDO does not support rewinding or seeking. Use Result::buffered() before first consume of the result.");
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
				0 => self::$types[$field['sqlsrv:decl_type']]
					?? self::$types[substr($field['sqlsrv:decl_type'], 0, -9)] // strip " identity" suffix
					?? self::TYPE_AS_IS,
				1 => $field['sqlsrv:decl_type'],
			];
		}

		return $types;
	}


	public function getRowsCount(): int
	{
		return $this->statement->rowCount();
	}
}
