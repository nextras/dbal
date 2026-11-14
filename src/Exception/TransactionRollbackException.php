<?php declare(strict_types = 1);

namespace Nextras\Dbal\Exception;


use RuntimeException;
use Throwable;


/**
 * The transaction failed and could not be rolled back. The original failure is available through getPrevious().
 */
class TransactionRollbackException extends RuntimeException
{
	public function __construct(
		Throwable $transactionException,
		private readonly Throwable $rollbackException,
		private readonly Throwable|null $disconnectException = null,
	)
	{
		parent::__construct('Transaction rollback failed: ' . $rollbackException->getMessage(), 0, $transactionException);
	}


	public function getRollbackException(): Throwable
	{
		return $this->rollbackException;
	}


	public function getDisconnectException(): Throwable|null
	{
		return $this->disconnectException;
	}
}
