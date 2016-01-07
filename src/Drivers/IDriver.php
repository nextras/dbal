<?php

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
	 * @param  array $params
	 */
	public function connect(array $params);


	/**
	 * Disconnects from the database.
	 */
	public function disconnect();


	/**
	 * Returns true, if there is created connection.
	 * @return bool
	 */
	public function isConnected();


	/**
	 * Returns connection resource.
	 * @return mixed
	 */
	public function getResourceHandle();


	/**
	 * Runs query and returns the result. Returns NULL if query does not select data.
	 * @param  string $query
	 * @return Result|NULL
	 */
	public function query($query);


	/**
	 * Returns the last inseted id.
	 * @param  string|NULL $sequenceName
	 * @return mixed
	 */
	public function getLastInsertedId($sequenceName = NULL);


	/**
	 * Returns number of affected rows.
	 * @return int
	 */
	public function getAffectedRows();


	/**
	 * Creates database plafrom.
	 * @param  Connection $connection
	 * @return IPlatform
	 */
	public function createPlatform(Connection $connection);


	/**
	 * Returns server version in X.Y.Z format.
	 * @return string
	 */
	public function getServerVersion();


	/**
	 * Pings server.
	 * @return bool
	 */
	public function ping();


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
	public function convertToPhp($value, $nativeType);


	/**
	 * @param  string $value
	 * @return string
	 */
	public function convertStringToSql($value);


	/**
	 * @param  string $value
	 * @param  int $mode -1 = left, 0 = both, 1 = right
	 * @return mixed
	 */
	public function convertLikeToSql($value, $mode);


	/**
	 * @param  bool $value
	 * @return string
	 */
	public function convertBoolToSql($value);


	/**
	 * @param  string $value
	 * @return string
	 */
	public function convertIdentifierToSql($value);


	/**
	 * @param  \DateTime|\DateTimeImmutable|\DateTimeInterface $value
	 * @return string
	 */
	public function convertDateTimeToSql($value);


	/**
	 * @param  \DateTime|\DateTimeImmutable|\DateTimeInterface $value
	 * @return string
	 */
	public function convertDateTimeSimpleToSql($value);


	/**
	 * @param  \DateInterval $value
	 * @return string
	 */
	public function convertDateIntervalToSql($value);


	/**
	 * @param  string $value
	 * @return string
	 */
	public function convertBlobToSql($value);


	/**
	 * Adds driver-specific limit clause to the query.
	 * @param  string $query
	 * @param  int|NULL $limit
	 * @param  int|NULL $offset
	 * @return string
	 */
	public function modifyLimitQuery($query, $limit, $offset);
}
