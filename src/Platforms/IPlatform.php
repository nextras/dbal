<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Platforms;


interface IPlatform
{
	const SUPPORT_MULTI_COLUMN_IN = 1;
	const SUPPORT_QUERY_EXPLAIN = 2;


	/**
	 * Returns platform name.
	 */
	public function getName(): string;


	/**
	 * Returns list of tables names indexed by table name.
	 */
	public function getTables(): array;


	/**
	 * Returns list of table columns metadata, indexed by column name.
	 */
	public function getColumns(string $table): array;


	/**
	 * Returns list of table foreign keys, indexed by column name.
	 */
	public function getForeignKeys(string $table): array;


	/**
	 * Returns primary sequence name for the table.
	 * If not supported nor present, returns a null.
	 */
	public function getPrimarySequenceName(string $table): ?string;


	public function isSupported(int $feature): bool;
}
