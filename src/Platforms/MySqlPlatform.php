<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms;


use Nextras\Dbal\Connection;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Dbal\Utils\StrictObjectTrait;


class MySqlPlatform implements IPlatform
{
	use StrictObjectTrait;


	public const NAME = 'mysql';

	/** @var Connection */
	private $connection;


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}


	public function getName(): string
	{
		return static::NAME;
	}


	/** @inheritDoc */
	public function getTables(?string $schema = null): array
	{
		$result = $this->connection->query('
			SELECT
				TABLE_SCHEMA,
				TABLE_NAME,
				TABLE_TYPE
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = COALESCE(%?s, DATABASE())
		', $schema);

		$tables = [];
		foreach ($result as $row) {
			$table = new Table();
			$table->name = (string) $row->TABLE_NAME;
			$table->schema = (string) $row->TABLE_SCHEMA;
			$table->isView = $row->TABLE_TYPE === 'VIEW';

			$tables[$table->getNameFqn()] = $table;
		}
		return $tables;
	}


	/** @inheritDoc */
	public function getColumns(string $table): array
	{
		$columns = [];
		foreach ($this->connection->query('SHOW FULL COLUMNS FROM %table', $table) as $row) {
			$type = explode('(', $row->Type);

			$column = new Column();
			$column->name = (string) $row->Field;
			$column->type = \strtoupper($type[0]);
			$column->size = isset($type[1]) ? (int) $type[1] : null;
			$column->default = $row->Default !== null ? (string) $row->Default : null;
			$column->isPrimary = $row->Key === 'PRI';
			$column->isAutoincrement = $row->Extra === 'auto_increment';
			$column->isUnsigned = (bool) \strstr($row->Type, 'unsigned');
			$column->isNullable = $row->Null === 'YES';
			$column->meta = [];

			$columns[$column->name] = $column;
		}
		return $columns;
	}


	/** @inheritDoc */
	public function getForeignKeys(string $table): array
	{
		$parts = \explode('.', $table);
		if (\count($parts) === 2) {
			$db = $parts[0];
			$table = $parts[1];
		} else {
			$db = null;
		}

		$result = $this->connection->query('
			SELECT
				CONSTRAINT_NAME,
				CONSTRAINT_SCHEMA,
				COLUMN_NAME,
				REFERENCED_TABLE_NAME,
				REFERENCED_COLUMN_NAME,
				REFERENCED_TABLE_SCHEMA
			FROM
				information_schema.KEY_COLUMN_USAGE
			WHERE
				TABLE_SCHEMA = COALESCE(%?s, DATABASE())
				AND REFERENCED_TABLE_NAME IS NOT NULL
				AND TABLE_NAME = %s
			ORDER BY
				CONSTRAINT_NAME
		', $db, $table);

		/** @var array<string, ForeignKey> $keys */
		$keys = [];
		foreach ($result as $row) {
			$foreignKey = new ForeignKey();
			$foreignKey->name = (string) $row->CONSTRAINT_NAME;
			$foreignKey->schema = (string) $row->CONSTRAINT_SCHEMA;
			$foreignKey->column = (string) $row->COLUMN_NAME;
			$foreignKey->refTable = (string) $row->REFERENCED_TABLE_NAME;
			$foreignKey->refTableSchema = (string) $row->REFERENCED_TABLE_SCHEMA;
			$foreignKey->refColumn = (string) $row->REFERENCED_COLUMN_NAME;

			$keys[$foreignKey->column] = $foreignKey;
		}
		return $keys;
	}


	public function getPrimarySequenceName(string $table): ?string
	{
		return null;
	}


	public function isSupported(int $feature): bool
	{
		static $supported = [
			self::SUPPORT_MULTI_COLUMN_IN => true,
			self::SUPPORT_QUERY_EXPLAIN => true,
		];
		return isset($supported[$feature]);
	}
}
