<?php declare(strict_types = 1);

namespace Nextras\Dbal;

use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\StrictObjectTrait;


class MultiLogger implements ILogger
{
	use StrictObjectTrait;


	/**
	 * @var ILogger[]
	 * @phpstan-var array<string, ILogger>
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


	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result, ?DriverException $exception): void
	{
		foreach ($this->loggers as $logger) {
			$logger->onQuery($sqlQuery, $timeTaken, $result, $exception);
		}
	}
}
