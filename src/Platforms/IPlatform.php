<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms;


use DateInterval;
use DateTimeInterface;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\MultiQueryParser\IMultiQueryParser;


interface IPlatform
{
	public const SUPPORT_MULTI_COLUMN_IN = 1;
	public const SUPPORT_QUERY_EXPLAIN = 2;
	public const SUPPORT_WHITESPACE_EXPLAIN = 3;


	/**
	 * Returns platform name.
	 */
	public function getName(): string;


	/**
	 * Returns list of tables names indexed by fully qualified unescaped table name.
	 * If no schema is provided, it uses the current schema name (search path).
	 * @return array<string, Table>
	 */
	public function getTables(?string $schema = null): array;


	/**
	 * Returns list of table columns metadata, indexed by column name.
	 * @return array<string, Column>
	 */
	public function getColumns(string $table, ?string $schema = null): array;


	/**
	 * Returns list of table foreign keys, indexed by column name.
	 * @return array<string, ForeignKey>
	 */
	public function getForeignKeys(string $table, ?string $schema = null): array;


	/**
	 * Returns primary sequence name for the table.
	 * If not supported nor present, returns a null.
	 */
	public function getPrimarySequenceName(string $table, ?string $schema = null): ?string;


	/**
	 * Formats string value to SQL string.
	 */
	public function formatString(string $value): string;


	/**
	 * Formats left/right/both LIKE wildcard string value to SQL string.
	 * @param int $mode -1 = left, 0 = both, 1 = right
	 * @return mixed
	 */
	public function formatStringLike(string $value, int $mode);


	/**
	 * Formats Json value to SQL string.
	 */
	public function formatJson(mixed $value): string;


	/**
	 * Formats boolean to SQL string.
	 */
	public function formatBool(bool $value): string;


	/**
	 * Formats column or dot separated identifier to SQL string.
	 */
	public function formatIdentifier(string $value): string;


	/**
	 * Formats time-zone aware DateTimeInterface instance to SQL string.
	 */
	public function formatDateTime(DateTimeInterface $value): string;


	/**
	 * Formats local DateTimeInterface instance to SQL string.
	 */
	public function formatLocalDateTime(DateTimeInterface $value): string;


	/**
	 * Formats local date from DateTimeInterface instance to SQL string.
	 */
	public function formatLocalDate(DateTimeInterface $value): string;


	/**
	 * Formats DateInterval to SQL string.
	 */
	public function formatDateInterval(DateInterval $value): string;


	/**
	 * Formats blob value to SQL string.
	 */
	public function formatBlob(string $value): string;


	/**
	 * Formats LIMIT & OFFSET values to SQL string.
	 */
	public function formatLimitOffset(?int $limit, ?int $offset): string;


	/**
	 * Returns SQL file parser
	 * !!!This function requires nextras/multi-query-parser dependency!!!
	 */
	public function createMultiQueryParser(): IMultiQueryParser;


	/**
	 * Checks whether any feature from IPlatform::SUPPORT_* is supported.
	 * @internal
	 */
	public function isSupported(int $feature): bool;
}
