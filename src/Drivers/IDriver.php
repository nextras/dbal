<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;


interface IDriver
{

	/**
	 * Returns connection resource.
	 * @internal
	 * @return mixed
	 */
	public function getResourceHandle();


	/**
	 * Runs query and returns the result.
	 * @param  string $query
	 * @return IRowsetAdapter
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

}
