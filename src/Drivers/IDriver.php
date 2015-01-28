<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\Dbal\Exceptions\DbalException;
use Nextras\Dbal\Result\Result;


interface IDriver
{
	/** @const data types, which driver converts to sql */
	const TYPE_STRING = 1;
	const TYPE_BOOL = 2;
	const TYPE_IDENTIFIER = 3;


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
	 * @param  IDriverException $exception
	 * @return DbalException
	 */
	public function convertException(IDriverException $exception);


	/**
	 * Returns connection resource.
	 * @return mixed
	 */
	public function getResourceHandle();


	/**
	 * Runs query and returns the result.
	 * @param  string $query
	 * @return Result
	 */
	public function nativeQuery($query);


	/**
	 * Returns the last inseted id.
	 * @param  string|NULL  $sequenceName
	 * @return mixed
	 */
	public function getLastInsertedId($sequenceName = NULL);


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
	 * @return mixed
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
