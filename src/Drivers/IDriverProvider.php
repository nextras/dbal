<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\DBAL\Exceptions\DbalException;


interface IDriverProvider
{

	/**
	 * Creates connection to database.
	 * @param  array  $params
	 * @param  string $username
	 * @param  string $password
	 * @return IDriver
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

}
