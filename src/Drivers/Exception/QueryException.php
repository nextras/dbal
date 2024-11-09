<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


use Exception;


class QueryException extends DriverException
{
	public function __construct(
		string $message,
		int $errorCode = 0,
		string $errorSqlState = '',
		Exception|null $previousException = null,
		private readonly ?string $sqlQuery = null
	)
	{
		parent::__construct($message, $errorCode, $errorSqlState, $previousException);
	}


	public function getSqlQuery(): ?string
	{
		return $this->sqlQuery;
	}
}
