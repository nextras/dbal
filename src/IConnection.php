<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;


interface IConnection
{
	public const TRANSACTION_READ_UNCOMMITTED = 1;
	public const TRANSACTION_READ_COMMITTED = 2;
	public const TRANSACTION_REPEATABLE_READ = 3;
	public const TRANSACTION_SERIALIZABLE = 4;


	/**
	 * Executes a query.
	 * @param  mixed ...$args
	 * @throws QueryException
	 */
	public function query(...$args): ?Result;


	/**
	 * @param  string|array $query
	 * @param  array $args
	 * @throws QueryException
	 */
	public function queryArgs($query, array $args = []): ?Result;


	/**
	 * Returns last inserted ID.
	 * @return int|string
	 */
	public function getLastInsertedId(string $sequenceName = NULL);


	/**
	 * Returns number of affected rows.
	 */
	public function getAffectedRows(): int;


	public function getPlatform(): IPlatform;


	public function createQueryBuilder(): QueryBuilder;


	public function setTransactionIsolationLevel(int $level): void;


	/**
	 * Performs operation in a transaction.
	 * @param  callable $callback function(Connection $conn): mixed
	 * @return mixed value returned by callback
	 * @throws \Exception
	 */
	public function transactional(callable $callback);


	/**
	 * Begins a transaction.
	 * @throws DriverException
	 */
	public function beginTransaction(): void;


	/**
	 * Commits the current transaction.
	 * @throws DriverException
	 */
	public function commitTransaction(): void;


	/**
	 * Cancels the current transaction.
	 * @throws DriverException
	 */
	public function rollbackTransaction(): void;


	/**
	 * Creates a savepoint.
	 * @throws DriverException
	 */
	public function createSavepoint(string $name): void;


	/**
	 * Releases the savepoint.
	 * @throws DriverException
	 */
	public function releaseSavepoint(string $name): void;


	/**
	 * Rollbacks the savepoint.
	 * @throws DriverException
	 */
	public function rollbackSavepoint(string $name): void;


	/**
	 * Pings a database connection and tries to reconnect it if it is broken.
	 */
	public function ping(): bool;
}
