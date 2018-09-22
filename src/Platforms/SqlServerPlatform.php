<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Platforms;

use Nextras\Dbal\Connection;


class SqlServerPlatform implements IPlatform
{
	/** @var Connection */
	private $connection;


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}


	public function getName(): string
	{
		return 'mssql';
	}


	public function getTables(): array
	{
		$result = $this->connection->query("
 			SELECT TABLE_NAME, TABLE_TYPE
 			FROM information_schema.tables
 			ORDER BY TABLE_NAME
 		");

		$tables = [];
		foreach ($result as $row) {
			$tables[$row->TABLE_NAME] = [
				'name' => $row->TABLE_NAME,
				'is_view' => $row->TABLE_TYPE === 'VIEW',
			];
		}
		return $tables;
	}


	public function getColumns(string $table): array
	{
		$result = $this->connection->query("
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
					BIT, COLUMNPROPERTY(object_id([a].[TABLE_NAME]), [a].[COLUMN_NAME], 'IsIdentity')
				) AS [is_autoincrement],
				CONVERT(BIT, 0) AS [is_unsigned], -- not available in MS SQL
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
			ORDER BY [a].[ORDINAL_POSITION]
		", $table);
		$columns = [];
		foreach ($result as $row) {
			$columns[$row->name] = $row->toArray();
		}

		return $columns;
	}


	public function getForeignKeys(string $table): array
	{
		$result = $this->connection->query("
			SELECT
				[a].[CONSTRAINT_NAME] AS [name],
				[d].[COLUMN_NAME] AS [column],
				[c].[TABLE_NAME] AS [ref_table],
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
			WHERE [b].[TABLE_NAME] = %s
			ORDER BY [d].[COLUMN_NAME]
		", $table);
		$keys = [];
		foreach ($result as $row) {
			$keys[$row->column] = $row->toArray();
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
		];
		return isset($supported[$feature]);
	}
}
