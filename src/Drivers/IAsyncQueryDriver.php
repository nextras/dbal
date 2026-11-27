<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers;


use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Result\Result;


/**
 * A driver able to send a query without waiting for its result, so that several connections can execute queries
 * concurrently without an event loop. At most one query may be pending per driver; while it is pending, the driver
 * must not be used for anything else until {@see reapQuery()} is called.
 */
interface IAsyncQueryDriver extends IDriver
{
	/**
	 * Returns true if this PHP build allows sending queries asynchronously (e.g. mysqli requires mysqlnd).
	 */
	public function isAsyncQuerySupported(): bool;

	/**
	 * Sends the query without waiting for its result.
	 * @throws DriverException if the query cannot be sent
	 */
	public function sendQuery(string $query): void;

	/**
	 * Waits for the result of the query sent by {@see sendQuery()}; afterwards the driver accepts another query.
	 * Updates {@see getQueryElapsedTime()} (measured since {@see sendQuery()}) and {@see getAffectedRows()} as
	 * {@see query()} does.
	 * @throws DriverException the same exception {@see query()} throws for the same failure
	 */
	public function reapQuery(): Result;

	/**
	 * Returns true if a query sent by {@see sendQuery()} has not been reaped yet.
	 */
	public function hasPendingQuery(): bool;
}
