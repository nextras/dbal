<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Result\Result;


/**
 * @internal
 */
final class MultiLogger implements ILogger
{
	use StrictObjectTrait;


	/**
	 * @var array<string, ILogger>
	 */
	public $loggers = [];


	public function onConnect(): void
	{
		foreach ($this->loggers as $logger) {
			$logger->onConnect();
		}
	}


	public function onDisconnect(): void
	{
		foreach ($this->loggers as $logger) {
			$logger->onDisconnect();
		}
	}


	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result): void
	{
		foreach ($this->loggers as $logger) {
			$logger->onQuery($sqlQuery, $timeTaken, $result);
		}
	}


	public function onQueryException(string $sqlQuery, float $timeTaken, ?DriverException $exception): void
	{
		foreach ($this->loggers as $logger) {
			$logger->onQueryException($sqlQuery, $timeTaken, $exception);
		}
	}
}
