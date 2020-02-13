<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Platforms;

use Nextras\Dbal\Connection;


class PostgreSqlPlatform implements IPlatform
{
	/** @var Connection */
	private $connection;


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}


	public function getName(): string
	{
		return 'pgsql';
	}


	public function getTables(): array
	{
		$result = $this->connection->query("
			SELECT
				DISTINCT ON (c.relname)
				c.relname::varchar AS name,
				c.relkind = 'v' AS is_view,
				n.nspname::varchar || '.' || c.relname::varchar AS full_name
			FROM
				pg_catalog.pg_class AS c
				JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
			WHERE
				c.relkind IN ('r', 'v')
				AND n.nspname = ANY (pg_catalog.current_schemas(FALSE))
			ORDER BY
				c.relname
		");

		$tables = [];
		foreach ($result as $row) {
			$tables[$row->name] = $row->toArray();
		}
		return $tables;
	}


	public function getColumns(string $table): array
	{
		$result = $this->connection->query("
			SELECT
				a.attname::varchar AS name,
				upper(t.typname) AS type,
				CASE WHEN a.atttypmod = -1 THEN NULL ELSE a.atttypmod -4 END AS size,
				pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass)::varchar AS default,
				coalesce(co.contype = 'p', FALSE) AS is_primary,
				coalesce(co.contype = 'p' AND strpos(pg_get_expr(ad.adbin, ad.adrelid), 'nextval') = 1, FALSE) AS is_autoincrement,
				FALSE AS is_unsigned,
				NOT (a.attnotnull OR t.typtype = 'd' AND t.typnotnull) AS is_nullable,
				substring(pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass) from %s) AS sequence
			FROM
				pg_catalog.pg_attribute AS a
				JOIN pg_catalog.pg_class AS c ON a.attrelid = c.oid
				JOIN pg_catalog.pg_type AS t ON a.atttypid = t.oid
				LEFT JOIN pg_catalog.pg_attrdef AS ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
				LEFT JOIN pg_catalog.pg_constraint AS co ON co.connamespace = c.relnamespace AND contype = 'p' AND co.conrelid = c.oid AND a.attnum = ANY(co.conkey)
			WHERE
				c.relkind IN ('r', 'v')
				AND c.oid = %s::regclass
				AND a.attnum > 0
				AND NOT a.attisdropped
			ORDER BY
				a.attnum
		", "nextval[(]'\"?([^'\"]+)", $table);

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
				co.conname::varchar AS name,
				al.attname::varchar AS column,
				nf.nspname || '.' || cf.relname::varchar AS ref_table,
				af.attname::varchar AS ref_column
			FROM
				pg_catalog.pg_constraint AS co
				JOIN pg_catalog.pg_class AS cl ON co.conrelid = cl.oid
				JOIN pg_catalog.pg_class AS cf ON co.confrelid = cf.oid
				JOIN pg_catalog.pg_namespace AS nf ON nf.oid = cf.relnamespace
				JOIN pg_catalog.pg_attribute AS al ON al.attrelid = cl.oid AND al.attnum = %raw
				JOIN pg_catalog.pg_attribute AS af ON af.attrelid = cf.oid AND af.attnum = %raw
			WHERE
				co.contype = 'f'
				AND cl.oid = %s::regclass
				AND nf.nspname = ANY (pg_catalog.current_schemas(FALSE))
		", 'co.conkey[1]', 'co.confkey[1]', $table);

		$keys = [];
		foreach ($result as $row) {
			$keys[$row->column] = $row->toArray();
		}
		return $keys;
	}


	public function getPrimarySequenceName(string $table): ?string
	{
		foreach ($this->getColumns($table) as $column) {
			if ($column['is_primary']) {
				return $column['sequence'];
			}
		}
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
