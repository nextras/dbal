<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\Dbal\Connection;
use Nextras\Dbal\DriverException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Result\Result;


interface IDriver
{
	const TYPE_BOOL = 1;
	const TYPE_DATETIME = 2;
	const TYPE_DATETIME_SIMPLE = 3;
	const TYPE_IDENTIFIER = 4;
	const TYPE_STRING = 5;
	const TYPE_DATE_INTERVAL = 6;
	const TYPE_BLOB = 7;

	const TIMEZONE_AUTO_PHP_NAME = 'auto';
	const TIMEZONE_AUTO_PHP_OFFSET = 'auto-offset';


	/**
	 * Connects the driver to database.
	 * @internal
	 */
	public function connect(array $params, callable $loggedQueryCallback);


	/**
	 * Disconnects from the database.
	 * @internal
	 */
	public function disconnect();


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
	 * @internal
	 */
	public function query(string $query): Result;


	/**
	 * Returns the last inserted id.
	 * @internal
	 * @return mixed
	 */
	public function getLastInsertedId(string $sequenceName = null);


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
	public function setTransactionIsolationLevel(int $level);


	/**
	 * Begins a transaction.
	 * @internal
	 * @throws DriverException
	 */
	public function beginTransaction(): void;


	/**
	 * Commits the current transaction.
	 * @internal
	 * @throws DriverException
	 */
	public function commitTransaction(): void;


	/**
	 * Rollbacks the current transaction.
	 * @internal
	 * @throws DriverException
	 */
	public function rollbackTransaction(): void;


	/**
	 * Creates a savepoint.
	 * @internal
	 * @throws DriverException
	 */
	public function createSavepoint(string $name): void;


	/**
	 * Releases the savepoint.
	 * @internal
	 * @throws DriverException
	 */
	public function releaseSavepoint(string $name): void;


	/**
	 * Rollbacks the savepoint.
	 * @internal
	 * @throws DriverException
	 */
	public function rollbackSavepoint(string $name): void;


	/**
	 * Converts database value to php boolean.
	 * @param  mixed $nativeType
	 * @return mixed
	 */
	public function convertToPhp(string $value, $nativeType);


	public function convertStringToSql(string $value): string;


	public function convertJsonToSql($value): string;


	/**
	 * @param  int $mode -1 = left, 0 = both, 1 = right
	 * @return mixed
	 */
	public function convertLikeToSql(string $value, int $mode);


	public function convertBoolToSql(bool $value): string;


	public function convertIdentifierToSql(string $value): string;


	public function convertDateTimeToSql(\DateTimeInterface $value): string;


	public function convertDateTimeSimpleToSql(\DateTimeInterface $value): string;


	public function convertDateIntervalToSql(\DateInterval $value): string;


	public function convertBlobToSql(string $value): string;


	/**
	 * Adds driver-specific limit clause to the query.
	 */
	public function modifyLimitQuery(string $query, ?int $limit, ?int $offset): string;
}
