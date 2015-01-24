<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\Dbal\Result\IRowset;


interface IDriver
{
	/** @const data types, which driver converts to sql */
	const TYPE_STRING = 1;
	const TYPE_BOOL = 2;
	const TYPE_IDENTIFIER = 3;


	/**
	 * Returns connection resource.
	 * @return mixed
	 */
	public function getResourceHandle();


	/**
	 * Runs query and returns the result.
	 * @param  string $query
	 * @return IRowset
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
	 * Returns matching regexp of tokens, in which modifiers should have not been matched.
	 * @return string
	 */
	public function getTokenRegexp();

}
