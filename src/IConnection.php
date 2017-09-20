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
	 * @return void
	 * @throws ConnectionException
	 */
	public function connect();


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect();


	/**
	 * Reconnects to a database.
	 * @return void
	 */
	public function reconnect();


	/**
	 * Reconnects to a database with new configration. Unchanged configuration is reused.
	 * @return void
	 */
	public function reconnectWithConfig(array $config);


	public function getDriver(): IDriver;


	/**
	 * Returns connection configuration.
	 */
	public function getConfig(): array;


	/**
	 * Executes a query.
	 * @param  mixed ...$args
	 * @return Result|null
	 * @throws QueryException
	 */
	public function query(...$args);


	/**
	 * @param  string|array $query
	 * @param  array $args
	 * @return Result|null
	 * @throws QueryException
	 */
	public function queryArgs($query, array $args = []);


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
	 * @return void
	 * @throws DriverException
	 */
	public function beginTransaction();


	/**
	 * Commits the current transaction.
	 * @return void
	 * @throws DriverException
	 */
	public function commitTransaction();


	/**
	 * Cancels the current transaction.
	 * @return void
	 * @throws DriverException
	 */
	public function rollbackTransaction();


	/**
	 * Creates a savepoint.
	 * @return void
	 * @throws DriverException
	 */
	public function createSavepoint(string $name);


	/**
	 * Releases the savepoint.
	 * @return void
	 * @throws DriverException
	 */
	public function releaseSavepoint(string $name);


	/**
	 * Rollbacks the savepoint.
	 * @return void
	 * @throws DriverException
	 */
	public function rollbackSavepoint(string $name);


	/**
	 * Pings a database connection and returns true if the connection is alive.
	 * @example
	 *     if (!$connection->ping()) {
	 *         $connection->reconnect();
	 *     }
	 * @return bool
	 */
	public function ping(): bool;
}
