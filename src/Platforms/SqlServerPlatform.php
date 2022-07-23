<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms;


use DateInterval;
use DateTimeInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Dbal\Utils\JsonHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function count;
use function explode;


class SqlServerPlatform implements IPlatform
{
	use StrictObjectTrait;


	public const NAME = 'mssql';

	/** @var IConnection */
	private $connection;

	/** @var IDriver */
	private $driver;


	public function __construct(IConnection $connection)
	{
		$this->connection = $connection;
		$this->driver = $connection->getDriver();
	}


	public function getName(): string
	{
		return static::NAME;
	}


	/** @inheritDoc */
	public function getTables(?string $schema = null): array
	{
		$result = $this->connection->query(/** @lang GenericSQL */ "
			SELECT TABLE_NAME, TABLE_TYPE, TABLE_SCHEMA
 			FROM information_schema.tables
			WHERE TABLE_SCHEMA = COALESCE(%?s, SCHEMA_NAME())
 			ORDER BY TABLE_NAME
		", $schema);

		$tables = [];
		foreach ($result as $row) {
			$table = new Table();
			$table->name = (string) $row->TABLE_NAME;
			$table->schema = (string) $row->TABLE_SCHEMA;
			$table->isView = $row->TABLE_TYPE === 'VIEW';

			$tables[$table->getUnescapedFqn()] = $table;
		}
		return $tables;
	}


	/** @inheritDoc */
	public function getColumns(string $table, ?string $schema = null): array
	{
		$result = $this->connection->query(/** @lang GenericSQL */ "
			SELECT
				[a].[COLUMN_NAME] AS [name],
				UPPER([a].[DATA_TYPE]) AS [type],
				CASE
					WHEN [a].[CHARACTER_MAXIMUM_LENGTH] IS NOT NULL THEN [a].[CHARACTER_MAXIMUM_LENGTH]
					WHEN  [a].[NUMERIC_PRECISION] IS NOT NULL THEN [a].[NUMERIC_PRECISION]
					ELSE NULL
				END AS [size],
				[a].[COLUMN_DEFAULT] AS [default],
				CASE
					WHEN [b].[CONSTRAINT_TYPE] = 'PRIMARY KEY'
					THEN CONVERT(BIT, 1)
					ELSE CONVERT(BIT, 0)
				END AS [is_primary],
				CONVERT(
					BIT, COLUMNPROPERTY(object_id(CONCAT([a].[TABLE_SCHEMA], '.', [a].[TABLE_NAME])), [a].[COLUMN_NAME], 'IsIdentity')
				) AS [is_autoincrement],
				CASE
					WHEN [a].[IS_NULLABLE] = 'YES'
					THEN CONVERT(BIT, 1)
					ELSE CONVERT(BIT, 0)
				END AS [is_nullable]
			FROM [INFORMATION_SCHEMA].[COLUMNS] AS [a]
			LEFT JOIN (
				SELECT [c].[TABLE_SCHEMA], [c].[TABLE_CATALOG], [c].[TABLE_NAME], [c].[COLUMN_NAME], [d].[CONSTRAINT_TYPE]
				FROM [INFORMATION_SCHEMA].[CONSTRAINT_COLUMN_USAGE] AS [c]
				INNER JOIN [INFORMATION_SCHEMA].[TABLE_CONSTRAINTS] AS [d]
				ON ([d].[CONSTRAINT_NAME] = [c].[CONSTRAINT_NAME] AND [d].[CONSTRAINT_TYPE] = 'PRIMARY KEY')
			) AS [b] ON (
				[b].[TABLE_CATALOG] = [a].[TABLE_CATALOG] AND
				[b].[TABLE_SCHEMA] = [a].[TABLE_SCHEMA] AND
				[b].[TABLE_NAME] = [a].[TABLE_NAME] AND
				[b].[COLUMN_NAME] = [a].[COLUMN_NAME]
			)
			WHERE [a].[TABLE_NAME] = %s
				AND [a].[TABLE_SCHEMA] = COALESCE(%?s, SCHEMA_NAME())
			ORDER BY [a].[ORDINAL_POSITION]
		", $table, $schema);

		$columns = [];
		foreach ($result as $row) {
			$column = new Column();
			$column->name = (string) $row->name;
			$column->type = (string) $row->type;
			$column->size = $row->size !== null ? (int) $row->size : null;
			$column->default = $row->default !== null ? (string) $row->default : null;
			$column->isPrimary = (bool) $row->is_primary;
			$column->isAutoincrement = (bool) $row->is_autoincrement;
			$column->isUnsigned = false; // not available in SqlServer
			$column->isNullable = (bool) $row->is_nullable;
			$column->meta = [];

			$columns[$column->name] = $column;
		}

		return $columns;
	}


	/** @inheritDoc */
	public function getForeignKeys(string $table, ?string $schema = null): array
	{
		$result = $this->connection->query(/** @lang GenericSQL */ "
			SELECT
				[a].[CONSTRAINT_NAME] AS [name],
				[d].[COLUMN_NAME] AS [column],
				[d].[TABLE_SCHEMA] AS [schema],
				[c].[TABLE_NAME] AS [ref_table],
				[c].[TABLE_SCHEMA] AS [ref_table_schema],
				[e].[COLUMN_NAME] AS [ref_column]
			FROM [INFORMATION_SCHEMA].[REFERENTIAL_CONSTRAINTS] AS [a]
			INNER JOIN [INFORMATION_SCHEMA].[TABLE_CONSTRAINTS] AS [b]
				ON [a].[CONSTRAINT_NAME] = [b].[CONSTRAINT_NAME]
			INNER JOIN [INFORMATION_SCHEMA].[TABLE_CONSTRAINTS] AS [c]
				ON [a].[UNIQUE_CONSTRAINT_NAME] = [c].[CONSTRAINT_NAME]
			INNER JOIN [INFORMATION_SCHEMA].[KEY_COLUMN_USAGE] AS [d]
				ON [a].[CONSTRAINT_NAME] = [d].[CONSTRAINT_NAME]
			INNER JOIN (
				SELECT [i1].[TABLE_NAME], [i2].[COLUMN_NAME]
				FROM [INFORMATION_SCHEMA].[TABLE_CONSTRAINTS] AS [i1]
				INNER JOIN [INFORMATION_SCHEMA].[KEY_COLUMN_USAGE] AS [i2]
					ON [i1].[CONSTRAINT_NAME] = [i2].[CONSTRAINT_NAME]
				WHERE [i1].[CONSTRAINT_TYPE] = 'PRIMARY KEY'
			) AS [e] ON [e].[TABLE_NAME] = [c].[TABLE_NAME]
			WHERE [b].[TABLE_NAME] = %s AND [d].[TABLE_SCHEMA] = COALESCE(%?s, SCHEMA_NAME())
			ORDER BY [d].[COLUMN_NAME]
		", $table, $schema);

		$keys = [];
		foreach ($result as $row) {
			$foreignKey = new ForeignKey();
			$foreignKey->name = (string) $row->name;
			$foreignKey->schema = (string) $row->schema;
			$foreignKey->column = (string) $row->column;
			$foreignKey->refTable = (string) $row->ref_table;
			$foreignKey->refTableSchema = (string) $row->ref_table_schema;
			$foreignKey->refColumn = (string) $row->ref_column;

			$keys[$foreignKey->column] = $foreignKey;
		}
		return $keys;
	}


	public function getPrimarySequenceName(string $table, ?string $schema = null): ?string
	{
		return null;
	}


	public function formatString(string $value): string
	{
		return $this->driver->convertStringToSql($value);
	}


	public function formatStringLike(string $value, int $mode)
	{
		// https://docs.microsoft.com/en-us/sql/t-sql/language-elements/like-transact-sql
		$value = strtr($value, [
			"'" => "''",
			'%' => '[%]',
			'_' => '[_]',
			'[' => '[[]',
		]);
		return ($mode <= 0 ? "'%" : "'") . $value . ($mode >= 0 ? "%'" : "'");
	}


	public function formatJson($value): string
	{
		$encoded = JsonHelper::safeEncode($value);
		return $this->driver->convertStringToSql($encoded);
	}


	public function formatBool(bool $value): string
	{
		return $value ? '1' : '0';
	}


	public function formatIdentifier(string $value): string
	{
		return '[' . str_replace([']', '.'], [']]', '].['], $value) . ']';
	}


	public function formatDateTime(DateTimeInterface $value): string
	{
		return "CAST('" . $value->format('Y-m-d H:i:s.u P') . "' AS DATETIMEOFFSET)";
	}


	public function formatLocalDateTime(DateTimeInterface $value): string
	{
		return "CAST('" . $value->format('Y-m-d H:i:s.u') . "' AS DATETIME2)";
	}


	public function formatDateInterval(DateInterval $value): string
	{
		throw new NotSupportedException();
	}


	public function formatBlob(string $value): string
	{
		return '0x' . bin2hex($value);
	}


	public function formatLimitOffset(?int $limit, ?int $offset): string
	{
		$clause = 'OFFSET ' . ($offset !== null ? $offset : 0) . ' ROWS';
		if ($limit !== null) {
			$clause .= ' FETCH NEXT ' . $limit . ' ROWS ONLY';
		}
		return $clause;
	}


	public function isSupported(int $feature): bool
	{
		static $supported = [
		];
		return isset($supported[$feature]);
	}
}
