<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Result\Result;


/**
 * Support class to easily log only executed queries.
 *
 * <code>
 * $connection->addLogger(new CallbackQueryLogger(function (string $query, float $timeTaken, ?Result $result) {
 *      echo $query;
 * }));
 * </code>
 */
class CallbackQueryLogger implements ILogger
{
	/**
	 * @var callable(string $sqlQuery, float $timeTaken, ?Result $result): void
	 */
	private $callback;


	/**
	 * @param callable(string $sqlQuery, float $timeTaken, ?Result $result): void $callback
	 */
	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}


	public function onConnect(): void
	{
	}


	public function onDisconnect(): void
	{
	}


	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result): void
	{
		$callback = $this->callback;
		$callback($sqlQuery, $timeTaken, $result);
	}


	public function onQueryException(
		string $sqlQuery,
		float $timeTaken,
		?DriverException $exception
	): void
	{
	}
}
