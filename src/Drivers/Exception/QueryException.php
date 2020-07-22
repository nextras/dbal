<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


use Exception;


class QueryException extends DriverException
{
	/** @var string|null */
	private $sqlQuery;


	public function __construct(
		string $message,
		int $errorCode = 0,
		string $errorSqlState = '',
		Exception $previousException = null,
		?string $sqlQuery = null
	)
	{
		parent::__construct($message, $errorCode, $errorSqlState, $previousException);
		$this->sqlQuery = $sqlQuery;
	}


	public function getSqlQuery(): ?string
	{
		return $this->sqlQuery;
	}
}
