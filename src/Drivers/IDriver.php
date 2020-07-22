<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers;


use DateInterval;
use DateTimeInterface;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Result\Result;


interface IDriver
{
	public const TYPE_BOOL = 1;
	public const TYPE_DATETIME = 2;
	public const TYPE_DATETIME_SIMPLE = 3;
	public const TYPE_IDENTIFIER = 4;
	public const TYPE_STRING = 5;
	public const TYPE_DATE_INTERVAL = 6;
	public const TYPE_BLOB = 7;

	public const TIMEZONE_AUTO_PHP_NAME = 'auto';
	public const TIMEZONE_AUTO_PHP_OFFSET = 'auto-offset';


	/**
	 * Connects the driver to database.
	 * @phpstan-param array<string, mixed> $params
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
	 * @return mixed
	 */
	public function getResourceHandle();


	/**
	 * Runs query and returns a result. Returns a null if the query does not select any data.
	 * @throws DriverException
	 * @internal
	 */
	public function query(string $query): Result;


	/**
	 * Returns the last inserted id.
	 * @return mixed
	 * @internal
	 */
	public function getLastInsertedId(?string $sequenceName = null);


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
	public function createPlatform(Connection $connection): IPlatform;


	/**
	 * Returns server version in X.Y.Z format.
	 */
	public function getServerVersion(): string;


	/**
	 * Pings server.
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
	public function createSavepoint(string $name): void;


	/**
	 * Releases the savepoint.
	 * @throws DriverException
	 * @internal
	 */
	public function releaseSavepoint(string $name): void;


	/**
	 * Rollbacks the savepoint.
	 * @throws DriverException
	 * @internal
	 */
	public function rollbackSavepoint(string $name): void;


	/**
	 * Converts database value to php boolean.
	 * @param mixed $nativeType
	 * @return mixed
	 */
	public function convertToPhp(string $value, $nativeType);


	public function convertStringToSql(string $value): string;


	/**
	 * @param mixed $value
	 */
	public function convertJsonToSql($value): string;


	/**
	 * @param int $mode -1 = left, 0 = both, 1 = right
	 * @return mixed
	 */
	public function convertLikeToSql(string $value, int $mode);


	public function convertBoolToSql(bool $value): string;


	public function convertIdentifierToSql(string $value): string;


	public function convertDateTimeToSql(DateTimeInterface $value): string;


	public function convertDateTimeSimpleToSql(DateTimeInterface $value): string;


	public function convertDateIntervalToSql(DateInterval $value): string;


	public function convertBlobToSql(string $value): string;


	/**
	 * Adds driver-specific limit clause to the query.
	 */
	public function modifyLimitQuery(string $query, ?int $limit, ?int $offset): string;
}
