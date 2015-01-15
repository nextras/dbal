<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;


interface IDriverException
{

	/**
	 * Returns the error message.
	 * @return string
	 */
	public function getMessage();


	/**
	 * Returns the driver error code.
	 * @return int|string|mixed
	 */
	public function getErrorCode();


	/**
	 * Returns the SQL state error code.
	 * @return mixed
	 */
	public function getErrorSQLState();

}
