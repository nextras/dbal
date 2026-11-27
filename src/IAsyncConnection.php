<?php declare(strict_types = 1);

namespace Nextras\Dbal;


use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\PendingResult;


/**
 * A connection able to send a query without waiting for its result, so that queries on several connections run
 * concurrently. At most one query is pending per connection; any other operation on the connection waits for it.
 */
interface IAsyncConnection extends IConnection
{
	/**
	 * Returns true if the driver sends queries without waiting; otherwise the async methods run the query
	 * synchronously and return an already resolved {@see PendingResult}.
	 */
	public function supportsAsyncQueries(): bool;

	/**
	 * Sends the query without waiting for its result; {@see PendingResult::getResult()} waits for it.
	 * Committing a transaction throws the failure of a query in it whose result was not read.
	 * @throws DriverException if the query cannot be sent
	 */
	public function queryAsync(string $expression, mixed ...$args): PendingResult;

	/**
	 * @see queryAsync()
	 * @param string|array<mixed> $query
	 * @param array<mixed> $args
	 * @throws DriverException if the query cannot be sent
	 */
	public function queryArgsAsync(string|array $query, array $args = []): PendingResult;

	/**
	 * @see queryAsync()
	 * @throws DriverException if the query cannot be sent
	 */
	public function queryByQueryBuilderAsync(QueryBuilder $queryBuilder): PendingResult;
}
