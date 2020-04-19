<?php declare(strict_types = 1);

namespace NextrasTests\Dbal;

use Nextras\Dbal\DriverException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Result\Result;


class TestLogger implements ILogger
{
	/** @var array<mixed> */
	public $logged = [];

	/** @var bool */
	public $logQueries = false;


	public function onConnect(): void
	{
		$this->logged[] = 'connect';
	}


	public function onDisconnect(): void
	{
		$this->logged[] = 'disconnect';
	}


	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result, ?DriverException $exception): void
	{
		if ($this->logQueries) {
			$this->logged[] = [$sqlQuery, $timeTaken, $result, $exception];
		}
	}
}
