<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms;


use DateInterval;
use DateTimeInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Dbal\Utils\DateTimeHelper;
use Nextras\Dbal\Utils\JsonHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;
use Nextras\MultiQueryParser\IMultiQueryParser;
use Nextras\MultiQueryParser\PostgreSqlMultiQueryParser;
use function bin2hex;
use function str_replace;
use function strtr;
use function trim;


class PostgreSqlPlatform implements IPlatform
{
	use StrictObjectTrait;


	final public const NAME = 'pgsql';

	private readonly IDriver $driver;


	public function __construct(private readonly IConnection $connection)
	{
		$this->driver = $connection->getDriver();
	}


	public function getName(): string
	{
		return static::NAME;
	}


	/** @inheritDoc */
	public function getTables(?string $schema = null): array
	{
		// relkind options:
		// r = ordinary table, i = index, S = sequence, t = TOAST table, v = view, m = materialized view, c = composite type, f = foreign table, p = partitioned table, I = partitioned index
		$result = $this->connection->query(/** @lang GenericSQL */ "
			SELECT
				DISTINCT ON (c.relname)
				c.relname::varchar AS name,
				n.nspname::varchar AS schema,
				c.relkind = 'v' AS is_view
			FROM
				pg_catalog.pg_class AS c
				JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
			WHERE
				c.relkind IN ('r', 'v', 'm', 'f', 'p')
				AND n.nspname = ANY (
					CASE %?s IS NULL WHEN true THEN pg_catalog.current_schemas(FALSE) ELSE ARRAY[[%?s]] END
				)
			ORDER BY
				c.relname
		", $schema, $schema);

		$tables = [];
		foreach ($result as $row) {
			$table = new Table(
				fqnName: new Fqn((string) $row->schema, (string) $row->name),
				isView: (bool) $row->is_view,
			);
			$tables[$table->fqnName->getUnescaped()] = $table;
		}
		return $tables;
	}


	/** @inheritDoc */
	public function getColumns(string $table, ?string $schema = null): array
	{
		$tableArgs = $schema !== null ? [$schema, $table] : [$table];
		$result = $this->connection->query((/** @lang GenericSQL */ "
			SELECT
				a.attname::varchar AS name,
				UPPER(t.typname) AS type,
				CASE WHEN a.atttypmod = -1 THEN NULL ELSE a.atttypmod -4 END AS size,
				pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass)::varchar AS default,
				COALESCE(co.contype = 'p', FALSE) AS is_primary,
				COALESCE(co.contype = 'p' AND (strpos(pg_get_expr(ad.adbin, ad.adrelid), 'nextval') = 1 OR a.attidentity != ''), FALSE) AS is_autoincrement,
				NOT (a.attnotnull OR t.typtype = 'd' AND t.typnotnull) AS is_nullable,
				COALESCE(
			") . (
				count($tableArgs) > 1
					? "pg_get_serial_sequence('%table.%table', a.attname),"
					: "pg_get_serial_sequence('%table', a.attname),"
			)
			. (/** @lang GenericSQL */ "
					SUBSTRING(pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass) FROM %s)
				) AS sequence
			FROM
				pg_catalog.pg_attribute AS a
				JOIN pg_catalog.pg_class AS c ON a.attrelid = c.oid
				JOIN pg_catalog.pg_type AS t ON a.atttypid = t.oid
				LEFT JOIN pg_catalog.pg_attrdef AS ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
				LEFT JOIN pg_catalog.pg_constraint AS co ON co.connamespace = c.relnamespace AND contype = 'p' AND co.conrelid = c.oid AND a.attnum = ANY(co.conkey)
			WHERE
				c.relkind IN ('r', 'v', 'm', 'f', 'p')
				") . (
			count($tableArgs) > 1
				? "AND c.oid = '%table.%table'::regclass"
				: "AND c.oid = '%table'::regclass"
			) . (/** @lang GenericSQL */ "
				AND a.attnum > 0
				AND NOT a.attisdropped
			ORDER BY
				a.attnum
		"), ...$tableArgs, ...["nextval[(]'\"?([^'\"]+)"], ...$tableArgs);

		$columns = [];
		foreach ($result as $row) {
			$column = new Column(
				name: (string) $row->name,
				type: (string) $row->type,
				size: $row->size !== null ? (int) $row->size : null,
				default: $row->default !== null ? (string) $row->default : null,
				isPrimary: (bool) $row->is_primary,
				isAutoincrement: (bool) $row->is_autoincrement,
				isUnsigned: false,
				isNullable: (bool) $row->is_nullable,
				meta: isset($row->sequence) ? ['sequence' => $row->sequence] : [],
			);
			$columns[$column->name] = $column;
		}
		return $columns;
	}


	/** @inheritDoc */
	public function getForeignKeys(string $table, ?string $schema = null): array
	{
		$tableArgs = $schema !== null ? [$schema, $table] : [$table];
		$result = $this->connection->query((/** @lang PostgreSQL */ "
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
				") . (
			count($tableArgs) > 1
				? "AND cl.oid = '%table.%table'::regclass"
				: "AND cl.oid = '%table'::regclass"
			) . '
			ORDER BY at.attnum
			', ...$tableArgs);

		$keys = [];
		foreach ($result as $row) {
			$foreignKey = new ForeignKey(
				fqnName: new Fqn((string) $row->schema, (string) $row->name),
				column: (string) $row->column,
				refTable: new Fqn((string) $row->ref_table_schema, (string) $row->ref_table),
				refColumn: (string) $row->ref_column,
			);
			$keys[$foreignKey->column] = $foreignKey;
		}
		return $keys;
	}


	public function getPrimarySequenceName(string $table, ?string $schema = null): ?string
	{
		foreach ($this->getColumns($table, $schema) as $column) {
			if ($column->isPrimary) {
				return $column->meta['sequence'] ?? null;
			}
		}
		return null;
	}


	public function formatString(string $value): string
	{
		return $this->driver->convertStringToSql($value);
	}


	public function formatStringLike(string $value, int $mode)
	{
		$value = strtr($value, [
			"'" => "''",
			'\\' => '\\\\',
			'%' => '\\%',
			'_' => '\\_',
		]);
		return ($mode <= 0 ? "'%" : "'") . $value . ($mode >= 0 ? "%'" : "'");
	}


	public function formatJson(mixed $value): string
	{
		$encoded = JsonHelper::safeEncode($value);
		return $this->driver->convertStringToSql($encoded);
	}


	public function formatBool(bool $value): string
	{
		return $value ? 'TRUE' : 'FALSE';
	}


	public function formatIdentifier(string $value): string
	{
		return '"' . str_replace(['"', '.'], ['""', '"."'], $value) . '"';
	}


	public function formatDateTime(DateTimeInterface $value): string
	{
		$value = DateTimeHelper::convertToTimezone($value, $this->driver->getConnectionTimeZone());
		return "'" . $value->format('Y-m-d H:i:s.u') . "'::timestamptz";
	}


	public function formatLocalDateTime(DateTimeInterface $value): string
	{
		return "'" . $value->format('Y-m-d H:i:s.u') . "'::timestamp";
	}


	public function formatLocalDate(DateTimeInterface $value): string
	{
		return "'" . $value->format('Y-m-d') . "'::date";
	}


	public function formatDateInterval(DateInterval $value): string
	{
		return $value->format('P%yY%mM%dDT%hH%iM%sS');
	}


	public function formatBlob(string $value): string
	{
		return "E'\\\\x" . bin2hex($value) . "'";
	}


	public function formatLimitOffset(?int $limit, ?int $offset): string
	{
		$clause = '';
		if ($limit !== null) {
			$clause = 'LIMIT ' . $limit;
		}
		if ($offset !== null) {
			$clause = trim("$clause OFFSET $offset");
		}
		return $clause;
	}


	public function createMultiQueryParser(): IMultiQueryParser
	{
		if (!class_exists(PostgreSqlMultiQueryParser::class)) {
			throw new \RuntimeException("Missing nextras/multi-query-parser dependency. Install it first to use IPlatform::createMultiQueryParser().");
		}
		return new PostgreSqlMultiQueryParser();
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
