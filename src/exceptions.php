<?php declare(strict_types = 1);

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


	public function __construct(string $message, int $errorCode = 0, string $errorSqlState = NULL, Exception $previousException = NULL)
	{
		parent::__construct($message, 0, $previousException);
		$this->errorCode = $errorCode;
		$this->errorSqlState = (string) $errorSqlState;
	}


	public function getErrorCode(): int
	{
		return $this->errorCode;
	}


	public function getErrorSqlState(): string
	{
		return $this->errorSqlState;
	}

}


class QueryException extends DriverException
{
	/** @var string */
	private $sqlQuery;


	public function __construct(string $message, int $errorCode = 0, string $errorSqlState = '', Exception $previousException = NULL, string $sqlQuery = NULL)
	{
		parent::__construct($message, $errorCode, $errorSqlState, $previousException);
		$this->sqlQuery = (string) $sqlQuery;
	}


	public function getSqlQuery(): string
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
