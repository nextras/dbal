<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;


interface IConnection
{
	/**
	 * Executes a query.
	 * @param  mixed ...$args
	 * @return Result|null
	 * @throws QueryException
	 */
	public function query(...$args);


	/**
	 * @param  string|array $query
	 * @param  array $args
	 * @return Result|null
	 * @throws QueryException
	 */
	public function queryArgs($query, array $args = []);


	/**
	 * Returns last inserted ID.
	 * @return int|string
	 */
	public function getLastInsertedId(string $sequenceName = NULL);


	/**
	 * Returns number of affected rows.
	 */
	public function getAffectedRows(): int;


	public function getPlatform(): IPlatform;


	public function createQueryBuilder(): QueryBuilder;


	/**
	 * Performs operation in a transaction.
	 * @param  callable $callback function(Connection $conn): mixed
	 * @return mixed value returned by callback
	 * @throws \Exception
	 */
	public function transactional(callable $callback);


	/**
	 * Starts a transaction.
	 * @return void
	 * @throws DriverException
	 */
	public function beginTransaction();


	/**
	 * Commits the current transaction.
	 * @return void
	 * @throws DriverException
	 */
	public function commitTransaction();


	/**
	 * Cancels any uncommitted changes done during the current transaction.
	 * @return void
	 * @throws DriverException
	 */
	public function rollbackTransaction();


	/**
	 * Pings a database connection and tries to reconnect it if it is broken.
	 * @return bool
	 */
	public function ping(): bool;
}
