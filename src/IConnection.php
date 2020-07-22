<?php declare(strict_types = 1);

namespace Nextras\Dbal;


use Nextras\Dbal\Drivers\IDriver;
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
	 * Reconnects to a database with new configuration. Unchanged configuration is reused.
	 * @phpstan-param array<string, mixed> $config
	 */
	public function reconnectWithConfig(array $config): void;


	public function getDriver(): IDriver;


	/**
	 * Returns connection configuration.
	 * @phpstan-return array<string, mixed>
	 */
	public function getConfig(): array;


	/**
	 * Executes a query.
	 * @param mixed ...$args
	 * @phpstan-param mixed ...$args
	 * @throws QueryException
	 */
	public function query(...$args): Result;


	/**
	 * @param string|array $query
	 * @param array $args
	 * @phpstan-param string|array<mixed> $query
	 * @phpstan-param array<mixed> $args
	 * @throws QueryException
	 */
	public function queryArgs($query, array $args = []): Result;


	public function queryByQueryBuilder(QueryBuilder $queryBuilder): Result;


	/**
	 * Returns last inserted ID.
	 * @return int|string|null
	 */
	public function getLastInsertedId(?string $sequenceName = null);


	/**
	 * Returns number of affected rows.
	 */
	public function getAffectedRows(): int;


	public function getPlatform(): IPlatform;


	public function createQueryBuilder(): QueryBuilder;


	public function setTransactionIsolationLevel(int $level): void;


	/**
	 * Performs operation in a transaction.
	 * @param callable $callback function(Connection $conn): mixed
	 * @phpstan-param callable(Connection):mixed $callback
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
	 * Returns current connection's transaction nested index.
	 * 0 = no running transaction
	 * 1 = basic transaction
	 * >1 = nested transaction through save-points
	 */
	public function getTransactionNestedIndex(): int;


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


	/**
	 * Adds logger for observing connection queries & changes.
	 */
	public function addLogger(ILogger $logger): void;


	/**
	 * Removes logger.
	 */
	public function removeLogger(ILogger $logger): void;
}
