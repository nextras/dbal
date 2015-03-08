<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Exceptions\DbalException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Result\Result;


interface IDriver
{
	const TYPE_BOOL = 1;
	const TYPE_DATETIME = 2;
	const TYPE_DATETIME_SIMPLE = 3;
	const TYPE_IDENTIFIER = 4;
	const TYPE_STRING = 5;


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
	 * Converts IDriverException to exception representing the database error.
	 * @param  DriverException $exception
	 * @return DbalException
	 */
	public function convertException(DriverException $exception);


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
	public function nativeQuery($query);


	/**
	 * Returns the last inseted id.
	 * @param  string|NULL  $sequenceName
	 * @return mixed
	 */
	public function getLastInsertedId($sequenceName = NULL);


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
	 * @throws DbalException
	 */
	public function beginTransaction();


	/**
	 * Commits a transaction.
	 * @throws DbalException
	 */
	public function commitTransaction();


	/**
	 * Rollback a transaction.
	 * @throws DbalException
	 */
	public function rollbackTransaction();


	/**
	 * Converts database value to php boolean.
	 * @param  string $value
	 * @param  mixed  $nativeType
	 * @return mixed
	 */
	public function convertToPhp($value, $nativeType);


	/**
	 * Converts php value to database value.
	 * @param  mixed $value
	 * @param  mixed $type
	 * @return string
	 */
	public function convertToSql($value, $type);


	/**
	 * Adds driver-specific limit clause to the query.
	 * @param  string   $query
	 * @param  int|NULL $limit
	 * @param  int|NULL $offset
	 * @return string
	 */
	public function modifyLimitQuery($query, $limit, $offset);

}
