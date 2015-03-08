<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Exceptions;


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
