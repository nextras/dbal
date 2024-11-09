<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


use Exception;


class DriverException extends Exception
{
	public function __construct(
		string $message,
		private readonly int $errorCode = 0,
		private readonly string $errorSqlState = '',
		Exception|null $previousException = null
	)
	{
		parent::__construct($message, 0, $previousException);
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
