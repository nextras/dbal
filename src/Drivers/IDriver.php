<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\DBAL\Exceptions\DbalException;


interface IDriver
{

	/**
	 * Creates connection to database.
	 * @param  array  $params
	 * @param  string $username
	 * @param  string $password
	 * @return IConnection
	 */
	public function connect(array $params, $username, $password);


	/**
	 * Converts IDriverException to exception representing the database error.
	 *
	 * @param  string           $message
	 * @param  IDriverException $exception
	 * @return DbalException
	 */
	public function convertException($message, IDriverException $exception);


	/**
	 * Converts database value to php boolean.
	 * @param  string $value
	 * @param  mixed  $nativeType
	 * @return mixed
	 */
	public function convertToPhp($value, $nativeType);

}
