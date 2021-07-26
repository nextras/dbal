<?php declare(strict_types = 1);

namespace Nextras\Dbal;


use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Result\Result;


interface ILogger
{
	/**
	 * Called when connection gets connected to database.
	 */
	public function onConnect(): void;


	/**
	 * Called when connection gets disconnected from database.
	 */
	public function onDisconnect(): void;


	/**
	 * When SQL query is executed on connection.
	 */
	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result): void;


	/**
	 * When SQL query execution fails on connection.
	 */
	public function onQueryException(
		string $sqlQuery,
		float $timeTaken,
		?DriverException $exception
	): void;
}
