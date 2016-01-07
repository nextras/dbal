<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Exception;


class InvalidArgumentException extends \InvalidArgumentException
{
}


class InvalidStateException extends \LogicException
{
}


class IOException extends \RuntimeException
{
}


class NotImplementedException extends \LogicException
{
}


class NotSupportedException extends \LogicException
{
}


class ConnectionException extends DriverException
{
}




class DriverException extends Exception
{
	/** @var int */
	private $errorCode;

	/** @var string */
	private $errorSqlState;


	/**
	 * @param  string $message
	 * @param  int $errorCode
	 * @param  string $errorSqlState
	 * @param  Exception $previousException
	 */
	public function __construct($message, $errorCode = 0, $errorSqlState = '', Exception $previousException = NULL)
	{
		parent::__construct($message, 0, $previousException);
		$this->errorCode = (int) $errorCode;
		$this->errorSqlState = $errorSqlState;
	}


	public function getErrorCode()
	{
		return $this->errorCode;
	}


	public function getErrorSqlState()
	{
		return $this->errorSqlState;
	}

}


class QueryException extends DriverException
{
	/** @var string */
	private $sqlQuery;


	public function __construct($message, $errorCode = 0, $errorSqlState = '', $previousException = NULL, $sqlQuery = NULL)
	{
		parent::__construct($message, $errorCode, $errorSqlState, $previousException);
		$this->sqlQuery = (string) $sqlQuery;
	}


	public function getSqlQuery()
	{
		return $this->sqlQuery;
	}

}



abstract class ConstraintViolationException extends QueryException
{
}


class NotNullConstraintViolationException extends ConstraintViolationException
{
}


class ForeignKeyConstraintViolationException extends ConstraintViolationException
{
}


class UniqueConstraintViolationException extends ConstraintViolationException
{
}
