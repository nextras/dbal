<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryException;
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
	 */
	public function connect(array $params);


	/**
	 * Disconnects from the database.
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
	 * Runs query and returns the result. Returns NULL if query does not select data.
	 * @return Result|NULL
	 */
	public function query(string $query);


	/**
	 * Returns the last inserted id.
	 * @return mixed
	 */
	public function getLastInsertedId(string $sequenceName = NULL);


	/**
	 * Returns number of affected rows.
	 * @return int
	 */
	public function getAffectedRows(): int;


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
	 */
	public function ping(): bool;


	/**
	 * Begins a transaction.
	 * @throws QueryException
	 */
	public function beginTransaction();


	/**
	 * Commits a transaction.
	 * @throws QueryException
	 */
	public function commitTransaction();


	/**
	 * Rollback a transaction.
	 * @throws QueryException
	 */
	public function rollbackTransaction();


	/**
	 * Converts database value to php boolean.
	 * @param  string $value
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
	 * @param  int|NULL $limit
	 * @param  int|NULL $offset
	 */
	public function modifyLimitQuery(string $query, $limit, $offset): string;
}
