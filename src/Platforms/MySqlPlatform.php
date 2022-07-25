<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms;


use DateInterval;
use DateTimeInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Dbal\Utils\DateTimeHelper;
use Nextras\Dbal\Utils\JsonHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function addcslashes;
use function count;
use function explode;
use function str_replace;
use function strstr;
use function strtoupper;
use function trim;


class MySqlPlatform implements IPlatform
{
	use StrictObjectTrait;


	public const NAME = 'mysql';

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
		$result = $this->connection->query(/** @lang GenericSQL */ '
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

			$tables[$table->getUnescapedFqn()] = $table;
		}
		return $tables;
	}


	/** @inheritDoc */
	public function getColumns(string $table, ?string $schema = null): array
	{
		if ($schema !== null) {
			$query = $this->connection->query('SHOW FULL COLUMNS FROM %table.%table', $schema, $table);
		} else {
			$query = $this->connection->query('SHOW FULL COLUMNS FROM %table', $table);
		}
		$columns = [];
		foreach ($query as $row) {
			$type = explode('(', $row->Type);

			$column = new Column();
			$column->name = (string) $row->Field;
			$column->type = strtoupper($type[0]);
			$column->size = isset($type[1]) ? (int) $type[1] : null;
			$column->default = $row->Default !== null ? (string) $row->Default : null;
			$column->isPrimary = $row->Key === 'PRI';
			$column->isAutoincrement = $row->Extra === 'auto_increment';
			$column->isUnsigned = (bool) strstr($row->Type, 'unsigned');
			$column->isNullable = $row->Null === 'YES';
			$column->meta = [];

			$columns[$column->name] = $column;
		}
		return $columns;
	}


	/** @inheritDoc */
	public function getForeignKeys(string $table, ?string $schema = null): array
	{
		if($schema===null){
		    $parts = explode('.', $table);
		    if (count($parts) === 2) {
			$schema = $parts[0];
			$table = $parts[1];
		    } else {
			$schema = null;
		    }
        	}
		$result = $this->connection->query(/** @lang GenericSQL */ '
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
		', $schema, $table);

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


	public function getPrimarySequenceName(string $table, ?string $schema = null): ?string
	{
		return null;
	}


	public function formatString(string $value): string
	{
		return $this->driver->convertStringToSql($value);
	}


	public function formatStringLike(string $value, int $mode): string
	{
		$value = addcslashes(str_replace('\\', '\\\\', $value), "\x00\n\r\\'%_");
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
		return '`' . str_replace(['`', '.'], ['``', '`.`'], $value) . '`';
	}


	public function formatDateTime(DateTimeInterface $value): string
	{
		$value = DateTimeHelper::convertToTimezone($value, $this->driver->getConnectionTimeZone());
		return "'" . $value->format('Y-m-d H:i:s.u') . "'";
	}


	public function formatLocalDateTime(DateTimeInterface $value): string
	{
		return "'" . $value->format('Y-m-d H:i:s.u') . "'";
	}


	public function formatDateInterval(DateInterval $value): string
	{
		$totalHours = ((int) $value->format('%a')) * 24 + $value->h;
		if ($totalHours >= 839) {
			// see https://dev.mysql.com/doc/refman/5.0/en/time.html
			throw new InvalidArgumentException('Mysql cannot store interval bigger than 839h:59m:59s.');
		}
		return "'" . $value->format("%r{$totalHours}:%I:%S") . "'";
	}


	public function formatBlob(string $value): string
	{
		return '_binary' . $this->driver->convertStringToSql($value);
	}


	public function formatLimitOffset(?int $limit, ?int $offset): string
	{
		$clause = '';

		if ($limit !== null || $offset !== null) {
			// 18446744073709551615 is maximum of unsigned BIGINT
			// see http://dev.mysql.com/doc/refman/5.0/en/select.html
			$clause = 'LIMIT ' . ($limit !== null ? (string) $limit : '18446744073709551615');
		}

		if ($offset !== null) {
			$clause = trim("$clause OFFSET $offset");
		}

		return $clause;
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
