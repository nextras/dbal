<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms;


use Nextras\Dbal\Connection;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Dbal\Utils\StrictObjectTrait;


class PostgreSqlPlatform implements IPlatform
{
	use StrictObjectTrait;


	public const NAME = 'pgsql';

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
		$result = $this->connection->query("
			SELECT
				DISTINCT ON (c.relname)
				c.relname::varchar AS name,
				n.nspname::varchar AS schema,
				c.relkind = 'v' AS is_view
			FROM
				pg_catalog.pg_class AS c
				JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
			WHERE
				c.relkind IN ('r', 'v')
				AND n.nspname = ANY (
					CASE %?s IS NULL WHEN true THEN pg_catalog.current_schemas(FALSE) ELSE ARRAY[[%?s]] END
				)
			ORDER BY
				c.relname
		", $schema, $schema);

		$tables = [];
		foreach ($result as $row) {
			$table = new Table();
			$table->name = (string) $row->name;
			$table->schema = (string) $row->schema;
			$table->isView = (bool) $row->is_view;

			$tables[$table->getNameFqn()] = $table;
		}
		return $tables;
	}


	/** @inheritDoc */
	public function getColumns(string $table): array
	{
		$result = $this->connection->query("
			SELECT
				a.attname::varchar AS name,
				UPPER(t.typname) AS type,
				CASE WHEN a.atttypmod = -1 THEN NULL ELSE a.atttypmod -4 END AS size,
				pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass)::varchar AS default,
				COALESCE(co.contype = 'p', FALSE) AS is_primary,
				COALESCE(co.contype = 'p' AND strpos(pg_get_expr(ad.adbin, ad.adrelid), 'nextval') = 1, FALSE) AS is_autoincrement,
				NOT (a.attnotnull OR t.typtype = 'd' AND t.typnotnull) AS is_nullable,
				SUBSTRING(pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass) FROM %s) AS sequence
			FROM
				pg_catalog.pg_attribute AS a
				JOIN pg_catalog.pg_class AS c ON a.attrelid = c.oid
				JOIN pg_catalog.pg_type AS t ON a.atttypid = t.oid
				LEFT JOIN pg_catalog.pg_attrdef AS ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
				LEFT JOIN pg_catalog.pg_constraint AS co ON co.connamespace = c.relnamespace AND contype = 'p' AND co.conrelid = c.oid AND a.attnum = ANY(co.conkey)
			WHERE
				c.relkind IN ('r', 'v')
				AND c.oid = '%table'::regclass
				AND a.attnum > 0
				AND NOT a.attisdropped
			ORDER BY
				a.attnum
		", "nextval[(]'\"?([^'\"]+)", $table);

		$columns = [];
		foreach ($result as $row) {
			$column = new Column();
			$column->name = (string) $row->name;
			$column->type = (string) $row->type;
			$column->size = $row->size !== null ? (int) $row->size : null;
			$column->default = $row->default !== null ? (string) $row->default : null;
			$column->isPrimary = (bool) $row->is_primary;
			$column->isAutoincrement = (bool) $row->is_autoincrement;
			$column->isUnsigned = false;
			$column->isNullable = (bool) $row->is_nullable;
			$column->meta = isset($row->sequence) ? ['sequence' => $row->sequence] : [];

			$columns[$column->name] = $column;
		}
		return $columns;
	}


	/** @inheritDoc */
	public function getForeignKeys(string $table): array
	{
		$result = $this->connection->query("
			SELECT
				co.conname::varchar AS name,
				ns.nspname::varchar AS schema,
				at.attname::varchar AS column,
				clf.relname::varchar AS ref_table,
				nsf.nspname::varchar AS ref_table_schema,
				atf.attname::varchar AS ref_column
			FROM
				pg_catalog.pg_constraint AS co
				JOIN pg_catalog.pg_class AS cl ON co.conrelid = cl.oid
				JOIN pg_catalog.pg_class AS clf ON co.confrelid = clf.oid
				JOIN pg_catalog.pg_namespace AS ns ON ns.oid = cl.relnamespace
				JOIN pg_catalog.pg_namespace AS nsf ON nsf.oid = clf.relnamespace
				JOIN pg_catalog.pg_attribute AS at ON at.attrelid = cl.oid AND at.attnum = co.conkey[[1]]
				JOIN pg_catalog.pg_attribute AS atf ON atf.attrelid = clf.oid AND atf.attnum = co.confkey[[1]]
			WHERE
				co.contype = 'f'
				AND cl.oid = '%column'::regclass
		", $table);

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


	public function getPrimarySequenceName(string $table): ?string
	{
		foreach ($this->getColumns($table) as $column) {
			if ($column->isPrimary) {
				return $column->meta['sequence'] ?? null;
			}
		}
		return null;
	}


	public function isSupported(int $feature): bool
	{
		static $supported = [
			self::SUPPORT_MULTI_COLUMN_IN => true,
			self::SUPPORT_QUERY_EXPLAIN => true,
			self::SUPPORT_WHITESPACE_EXPLAIN => true,
		];
		return isset($supported[$feature]);
	}
}
