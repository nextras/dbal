<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers;


use DateTimeZone;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Result\Result;


interface IDriver
{
	public const TIMEZONE_AUTO_PHP_NAME = 'auto';
	public const TIMEZONE_AUTO_PHP_OFFSET = 'auto-offset';


	/**
	 * Connects the driver to a database.
	 * @param array<string, mixed> $params
	 * @internal
	 */
	public function connect(array $params, ILogger $logger): void;


	/**
	 * Disconnects from the database.
	 * @internal
	 */
	public function disconnect(): void;


	/**
	 * Returns true, if there is created connection.
	 */
	public function isConnected(): bool;


	/**
	 * Returns connection resource.
	 */
	public function getResourceHandle(): mixed;


	/**
	 * Returns connection time zone.
	 * If unsupported by driver, throws {@link NotSupportedException}.
	 */
	public function getConnectionTimeZone(): DateTimeZone;


	/**
	 * Runs query and returns a result. Returns a null if the query does not select any data.
	 * @throws DriverException
	 * @internal
	 */
	public function query(string $query): Result;


	/**
	 * Returns the last inserted id.
	 * @internal
	 */
	public function getLastInsertedId(string|Fqn|null $sequenceName = null): mixed;


	/**
	 * Returns number of affected rows.
	 * @internal
	 */
	public function getAffectedRows(): int;


	/**
	 * Returns time taken by the last query.
	 */
	public function getQueryElapsedTime(): float;


	/**
	 * Creates database platform.
	 */
	public function createPlatform(IConnection $connection): IPlatform;


	/**
	 * Returns server version in X.Y.Z format.
	 */
	public function getServerVersion(): string;


	/**
	 * Pings server.
	 * Returns true if the ping was successful and connection is alive.
	 * @internal
	 */
	public function ping(): bool;


	/**
	 * @internal
	 */
	public function setTransactionIsolationLevel(int $level): void;


	/**
	 * Begins a transaction.
	 * @throws DriverException
	 * @internal
	 */
	public function beginTransaction(): void;


	/**
	 * Commits the current transaction.
	 * @throws DriverException
	 * @internal
	 */
	public function commitTransaction(): void;


	/**
	 * Rollbacks the current transaction.
	 * @throws DriverException
	 * @internal
	 */
	public function rollbackTransaction(): void;


	/**
	 * Creates a savepoint.
	 * @throws DriverException
	 * @internal
	 */
	public function createSavepoint(string|Fqn $name): void;


	/**
	 * Releases the savepoint.
	 * @throws DriverException
	 * @internal
	 */
	public function releaseSavepoint(string|Fqn $name): void;


	/**
	 * Rollbacks the savepoint.
	 * @throws DriverException
	 * @internal
	 */
	public function rollbackSavepoint(string|Fqn $name): void;


	/**
	 * Converts string to safe escaped SQL expression including surrounding quotes.
	 */
	public function convertStringToSql(string $value): string;
}
