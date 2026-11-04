<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


class QueryException extends DriverException
{
	public function __construct(
		string $message,
		int $errorCode = 0,
		string $errorSqlState = '',
		\Throwable|null $previousException = null,
		private readonly string|null $sqlQuery = null,
	)
	{
		parent::__construct($message, $errorCode, $errorSqlState, $previousException);
	}


	public function getSqlQuery(): string|null
	{
		return $this->sqlQuery;
	}
}
