<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Exception;


class DriverException extends Exception
{
	/** @var int */
	private $errorCode;

	/** @var string */
	private $errorSqlState;


	/**
	 * @param string    $message
	 * @param int       $errorCode
	 * @param string    $errorSqlState
	 * @param Exception $previousException
	 */
	public function __construct($message, $errorCode, $errorSqlState, $previousException = NULL)
	{
		parent::__construct($message, 0, $previousException);
		$this->errorCode = (int) $errorCode;
		$this->errorSqlState = $errorSqlState;
	}


	public function getErrorCode()
	{
		return $this->errorCode;
	}


	public function getErrorSQLState()
	{
		return $this->errorSqlState;
	}

}
