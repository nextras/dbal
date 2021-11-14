<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms;


use DateInterval;
use DateTimeInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Dbal\Utils\DateTimeHelper;
use Nextras\Dbal\Utils\JsonHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function addcslashes;
use function bin2hex;
use function explode;
use function strtr;
use function strval;


class SqlitePlatform implements IPlatform
{
	use StrictObjectTrait;


	public const NAME = 'sqlite';

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
		return self::NAME;
	}


	public function getTables(?string $schema = null): array
	{
		$result = $this->connection->query(/** @lang SQLite */ "
			SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'
			UNION ALL
			SELECT name, type FROM sqlite_temp_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'
		");

		$tables = [];
		foreach ($result as $row) {
			$table = new Table();
			$table->name = $row->name;
			$table->schema = '';
			$table->isView = $row->type === 'view';
			$tables[$table->getNameFqn()] = $table;
		}
		return $tables;
	}


	public function getColumns(string $table): array
	{
		$raw = $this->connection->query(/** @lang SQLite */ "
			SELECT sql FROM sqlite_master WHERE type = 'table' AND name = %s
			UNION ALL
			SELECT sql FROM sqlite_temp_master WHERE type = 'table' AND name = %s
		", $table, $table)->fetchField();

		$result = $this->connection->query(/** @lang SQLite */ "
			PRAGMA table_info(%table)
		", $table);

		$columns = [];
		foreach ($result as $row) {
			$column = $row->name;
			$pattern = "~(\"$column\"|`$column`|\\[$column\\]|$column)\\s+[^,]+\\s+PRIMARY\\s+KEY\\s+AUTOINCREMENT~Ui";

			$type = explode('(', $row->type);
			$column = new Column();
			$column->name = (string) $row->name;
			$column->type = $type[0];
			$column->size = (int) ($type[1] ?? 0);
			$column->default = $row->dflt_value;
			$column->isPrimary = $row->pk === 1;
			$column->isAutoincrement = preg_match($pattern, (string) $raw) === 1;
			$column->isUnsigned = false;
			$column->isNullable = $row->notnull === 0;
			$columns[$column->name] = $column;
		}
		return $columns;
	}


	public function getForeignKeys(string $table): array
	{
		$result = $this->connection->query(/** @lang SQLite */ "
			PRAGMA foreign_key_list(%table)
		", $table);

		$foreignKeys = [];
		foreach ($result as $row) {
			$foreignKey = new ForeignKey();
			$foreignKey->name = (string) $row->id;
			$foreignKey->schema = '';
			$foreignKey->column = $row->from;
			$foreignKey->refTable = $row->table;
			$foreignKey->refTableSchema = '';
			$foreignKey->refColumn = $row->to;
			$foreignKeys[$foreignKey->getNameFqn()] = $foreignKey;
		}
		return $foreignKeys;
	}


	public function getPrimarySequenceName(string $table): ?string
	{
		return null;
	}


	public function formatString(string $value): string
	{
		return $this->driver->convertStringToSql($value);
	}


	public function formatStringLike(string $value, int $mode)
	{
		$value = addcslashes($this->formatString($value), '\\%_');
		return ($mode <= 0 ? "'%" : "'") . $value . ($mode >= 0 ? "%'" : "'") . " ESCAPE '\\'";
	}


	public function formatJson($value): string
	{
		$encoded = JsonHelper::safeEncode($value);
		return $this->formatString($encoded);
	}


	public function formatBool(bool $value): string
	{
		return $value ? '1' : '0';
	}


	public function formatIdentifier(string $value): string
	{
		return '[' . strtr($value, '[]', '  ') . ']';
	}


	public function formatDateTime(DateTimeInterface $value): string
	{
		$value = DateTimeHelper::convertToTimezone($value, $this->driver->getConnectionTimeZone());
		return strval($value->format('U') * 1000 + intval($value->format('u')));
	}


	public function formatLocalDateTime(DateTimeInterface $value): string
	{
		return "'" . $value->format('Y-m-d H:i:s.u') . "'";
	}


	public function formatDateInterval(DateInterval $value): string
	{
		return $value->format('P%yY%mM%dDT%hH%iM%sS');
	}


	public function formatBlob(string $value): string
	{
		return "X'" . bin2hex($value) . "'";
	}


	public function formatLimitOffset(?int $limit, ?int $offset): string
	{
		if ($limit === null && $offset === null) {
			return '';
		} elseif ($limit === null && $offset !== null) {
			return 'LIMIT -1 OFFSET ' . $offset;
		} elseif ($limit !== null && $offset === null) {
			return "LIMIT $limit";
		} else {
			return "LIMIT $limit OFFSET $offset";
		}
	}


	public function isSupported(int $feature): bool
	{
		static $supported = [
			self::SUPPORT_QUERY_EXPLAIN => true,
		];
		return isset($supported[$feature]);
	}
}
