<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoSqlite;


use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\Result\FullyBufferedResultAdapter;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Utils\StrictObjectTrait;
use PDO;
use PDOStatement;
use function strtolower;


class PdoSqliteResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	/** @var array<string, int> */
	protected static $types = [
		'int' => self::TYPE_INT,
		'integer' => self::TYPE_INT,
		'tinyint' => self::TYPE_INT,
		'smallint' => self::TYPE_INT,
		'mediumint' => self::TYPE_INT,
		'bigint' => self::TYPE_INT,
		'unsigned big int' => self::TYPE_INT,
		'int2' => self::TYPE_INT,
		'int8' => self::TYPE_INT,

		'real' => self::TYPE_FLOAT,
		'double' => self::TYPE_FLOAT,
		'double precision' => self::TYPE_FLOAT,
		'float' => self::TYPE_FLOAT,
		'numeric' => self::TYPE_FLOAT,
		'decimal' => self::TYPE_FLOAT,

		'bool' => self::TYPE_BOOL,

		'date' => self::TYPE_DATETIME,
		'datetime' => self::TYPE_DATETIME,
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
		return new FullyBufferedResultAdapter($this);
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
				// Sqlite does not return meta for special queries (PRAGMA, etc.)
				continue;
			}

			$type = strtolower($field['sqlite:decl_type'] ?? $field['native_type'] ?? '');

			$types[(string) $field['name']] = [
				0 => self::$types[$type] ?? dump(self::TYPE_AS_IS, $field),
				1 => $type,
			];
		}

		return $types;
	}


	public function getRowsCount(): int
	{
		return $this->statement->rowCount();
	}
}
