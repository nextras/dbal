<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;


interface IConnection
{
	const TRANSACTION_READ_UNCOMMITTED = 1;
	const TRANSACTION_READ_COMMITTED = 2;
	const TRANSACTION_REPEATABLE_READ = 3;
	const TRANSACTION_SERIALIZABLE = 4;


	/**
	 * Connects to a database.
	 * @throws ConnectionException
	 */
	public function connect(): void;


	/**
	 * Disconnects from a database.
	 */
	public function disconnect(): void;


	/**
	 * Reconnects to a database.
	 */
	public function reconnect(): void;


	/**
	 * Reconnects to a database with new configration. Unchanged configuration is reused.
	 */
	public function reconnectWithConfig(array $config): void;


	public function getDriver(): IDriver;


	/**
	 * Returns connection configuration.
	 */
	public function getConfig(): array;


	/**
	 * Executes a query.
	 * @param  mixed ...$args
	 * @throws QueryException
	 */
	public function query(...$args): Result;


	/**
	 * @param  string|array $query
	 * @param  array $args
	 * @throws QueryException
	 */
	public function queryArgs($query, array $args = []): Result;


	public function queryByQueryBuilder(QueryBuilder $queryBuilder): Result;


	/**
	 * Returns last inserted ID.
	 * @return int|string
	 */
	public function getLastInsertedId(string $sequenceName = null);


	/**
	 * Returns number of affected rows.
	 */
	public function getAffectedRows(): int;


	public function getPlatform(): IPlatform;


	public function createQueryBuilder(): QueryBuilder;


	public function setTransactionIsolationLevel(int $level);


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
	 * Pings a database connection and returns true if the connection is alive.
	 * @example
	 *     if (!$connection->ping()) {
	 *         $connection->reconnect();
	 *     }
	 */
	public function ping(): bool;
}
