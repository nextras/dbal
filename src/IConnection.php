<?php declare(strict_types = 1);

namespace Nextras\Dbal;


use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\PdoPgsql\PdoPgsqlDriver;
use Nextras\Dbal\Drivers\Pgsql\PgsqlDriver;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\Fqn;
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
	 * @param array<string, mixed> $config
	 */
	public function reconnectWithConfig(array $config): void;


	public function getDriver(): IDriver;


	/**
	 * Returns connection configuration.
	 * @return array<string, mixed>
	 */
	public function getConfig(): array;


	/**
	 * Executes an query.
	 *
	 * Write an SQL query first, use modifiers instead of actual variable values and pass the variable
	 * values as additional arguments.
	 *
	 * ```php
	 * $connection->query('SELECT * FROM books WHERE id = %i', $id);
	 * ```
	 * @param literal-string $expression
	 * @param mixed ...$args
	 * @throws QueryException
	 */
	public function query(string $expression, mixed ...$args): Result;


	/**
	 * @param string|array<mixed> $query
	 * @param array<mixed> $args
	 * @throws QueryException
	 */
	public function queryArgs(string|array $query, array $args = []): Result;


	public function queryByQueryBuilder(QueryBuilder $queryBuilder): Result;


	/**
	 * Returns last inserted ID.
	 *
	 * The sequence name's implementation depends on a particular database platform and driver.
	 *
	 * This method accepts the very same value obtained through platform reflection, e.g., through
	 * {@see IPlatform::getLastInsertedId} or alternatively through {@see IPlatform::getColumns()} and
	 * its {@see Column::$meta} property: `$column->meta['sequence']`.
	 *
	 * In case of {@see PgsqlDriver} or {@see PdoPgsqlDriver} the name is a string that may
	 * container double-quotes for handling cases sensitive names or names with special characters.
	 * I.e. `public."MySchemaName"` is a valid sequence name argument. Alternatively, you may pass a Fqn instance
	 * that will properly double-quote the schema and name.
	 *
	 * @return int|string|null
	 */
	public function getLastInsertedId(string|Fqn|null $sequenceName = null);


	/**
	 * Returns number of affected rows.
	 */
	public function getAffectedRows(): int;


	public function getPlatform(): IPlatform;


	public function createQueryBuilder(): QueryBuilder;


	public function setTransactionIsolationLevel(int $level): void;


	/**
	 * Performs operation in a transaction.
	 * @template T
	 * @param callable(Connection):T $callback
	 * @return T value returned by callback
	 * @throws \Exception
	 */
	public function transactional(callable $callback): mixed;


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
