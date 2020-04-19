<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

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
	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result, ?DriverException $exception): void;
}
