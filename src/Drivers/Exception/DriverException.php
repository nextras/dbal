<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


use Exception;


class DriverException extends Exception
{
	/** @var int */
	private $errorCode;

	/** @var string */
	private $errorSqlState;


	public function __construct(
		string $message,
		int $errorCode = 0,
		string $errorSqlState = null,
		Exception $previousException = null
	)
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
