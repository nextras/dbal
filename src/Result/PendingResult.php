<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use Closure;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Utils\StrictObjectTrait;
use Throwable;


/**
 * Result of a query sent by {@see Connection::queryAsync()}. The query runs on the database server
 * while the application continues; {@see getResult()} waits for it. The connection collects the pending query
 * automatically before it is used for anything else, so the result (or the failure) is never lost.
 */
final class PendingResult
{
	use StrictObjectTrait;

	private Result|null $result = null;

	private Throwable|null $exception = null;

	/** Whether the outcome was handed to the caller by {@see getResult()}. */
	private bool $isRead = false;


	private function __construct(
		/** @var (Closure(): Result)|null */
		private Closure|null $resolver,
	)
	{
	}


	/**
	 * @internal
	 * @param Closure(): Result $resolver waits for the query and returns its result
	 */
	public static function createPending(Closure $resolver): PendingResult
	{
		return new self($resolver);
	}


	/**
	 * @internal
	 */
	public static function createResolved(Result $result): PendingResult
	{
		$pendingResult = new self(null);
		$pendingResult->result = $result;
		return $pendingResult;
	}


	/**
	 * @internal
	 */
	public static function createFailed(Throwable $exception): PendingResult
	{
		$pendingResult = new self(null);
		$pendingResult->exception = $exception;
		return $pendingResult;
	}


	/**
	 * Waits for the query if it is still running and returns its result.
	 * @throws DriverException the query failure; the same instance on every call
	 */
	public function getResult(): Result
	{
		$this->resolve();
		$this->isRead = true;
		if ($this->exception !== null) {
			throw $this->exception;
		}
		if ($this->result === null) {
			throw new InvalidStateException('The pending query is being resolved.');
		}
		return $this->result;
	}


	/**
	 * Returns true if the query has finished and its result (or failure) has been collected.
	 */
	#[\NoDiscard]
	public function isResolved(): bool
	{
		return $this->result !== null || $this->exception !== null;
	}


	/**
	 * Waits for the query and stores its outcome without throwing it; returns the failure unless the caller
	 * has already read the outcome by {@see getResult()}.
	 * @internal Used by Connection.
	 */
	public function collect(): Throwable|null
	{
		$this->resolve();
		return $this->isRead ? null : $this->exception;
	}


	private function resolve(): void
	{
		$resolver = $this->resolver;
		if ($resolver === null) {
			return;
		}

		$this->resolver = null;
		try {
			$this->result = $resolver();
		} catch (Throwable $e) {
			$this->exception = $e;
		}
	}
}
