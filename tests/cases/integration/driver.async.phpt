<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\IAsyncQueryDriver;
use Nextras\Dbal\Drivers\Mysqli\MysqliDriver;
use Nextras\Dbal\Exception\InvalidStateException;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../bootstrap.php';


class DriverAsyncTest extends IntegrationTestCase
{
	public function setUp()
	{
		parent::setUp();
		$driver = $this->connection->getDriver();
		if (!$driver instanceof IAsyncQueryDriver) {
			Environment::skip('Driver does not implement IAsyncQueryDriver.');
		}
		if (!$driver->isAsyncQuerySupported()) {
			Environment::skip('Async queries are not supported by this PHP build.');
		}
	}


	public function testConcurrency()
	{
		$driverA = $this->getAsyncDriver($this->createConnection());
		$driverB = $this->getAsyncDriver($this->createConnection());

		$start = microtime(true);
		$driverA->sendQuery($this->getSleepSql(0.5));
		$driverB->sendQuery($this->getSleepSql(0.5));
		$driverA->reapQuery();
		$driverB->reapQuery();
		$elapsed = microtime(true) - $start;

		Assert::true($elapsed < 0.9, "Elapsed $elapsed s, expected concurrent execution.");
		Assert::true($driverA->getQueryElapsedTime() >= 0.45);
		Assert::true($driverB->getQueryElapsedTime() >= 0.45);
	}


	public function testPendingTransitions()
	{
		$driver = $this->getAsyncDriver($this->connection);
		Assert::false($driver->hasPendingQuery());
		$driver->sendQuery('SELECT 1 AS a');
		Assert::true($driver->hasPendingQuery());
		$result = $driver->reapQuery();
		Assert::false($driver->hasPendingQuery());
		Assert::same(1, $result->fetchField());

		$driver->sendQuery('SELECT FROM FROM foo');
		Assert::true($driver->hasPendingQuery());
		Assert::exception(fn() => $driver->reapQuery(), QueryException::class);
		Assert::false($driver->hasPendingQuery());

		Assert::exception(fn() => $driver->reapQuery(), InvalidStateException::class);
	}


	public function testGuardsWhilePending()
	{
		$driver = $this->getAsyncDriver($this->connection);
		$driver->sendQuery('SELECT 1');

		Assert::exception(fn() => $driver->query('SELECT 2'), InvalidStateException::class);
		Assert::exception(fn() => $driver->sendQuery('SELECT 2'), InvalidStateException::class);
		Assert::exception(fn() => $driver->getAffectedRows(), InvalidStateException::class);
		Assert::exception(fn() => $driver->getLastInsertedId('foo'), InvalidStateException::class);
		Assert::exception(fn() => $driver->ping(), InvalidStateException::class);
		Assert::exception(fn() => $driver->beginTransaction(), InvalidStateException::class);
		Assert::exception(fn() => $driver->commitTransaction(), InvalidStateException::class);
		Assert::exception(fn() => $driver->rollbackTransaction(), InvalidStateException::class);
		Assert::exception(fn() => $driver->createSavepoint('foo'), InvalidStateException::class);
		Assert::exception(fn() => $driver->releaseSavepoint('foo'), InvalidStateException::class);
		Assert::exception(fn() => $driver->rollbackSavepoint('foo'), InvalidStateException::class);
		Assert::exception(
			fn() => $driver->setTransactionIsolationLevel(Connection::TRANSACTION_READ_COMMITTED),
			InvalidStateException::class,
		);

		// the pending query is untouched by the rejected operations
		Assert::true($driver->hasPendingQuery());
		Assert::same(1, $driver->reapQuery()->fetchField());
		Assert::same(2, $driver->query('SELECT 2')->fetchField());
	}


	public function testAffectedRowsAndElapsedTime()
	{
		$driver = $this->getAsyncDriver($this->connection);
		$driver->query('CREATE TEMPORARY TABLE async_affected (id int)');

		$driver->sendQuery('INSERT INTO async_affected VALUES (1), (2), (3)');
		$driver->reapQuery();
		Assert::same(3, $driver->getAffectedRows());

		$driver->sendQuery('DELETE FROM async_affected WHERE id < 3');
		$driver->reapQuery();
		Assert::same(2, $driver->getAffectedRows());

		$driver->sendQuery($this->getSleepSql(0.2));
		$driver->reapQuery();
		Assert::true($driver->getQueryElapsedTime() >= 0.15);
		Assert::true($driver->getQueryElapsedTime() < 5);
	}


	public function testExceptionsAreIdenticalToQuery()
	{
		$this->initData($this->connection);
		$driver = $this->getAsyncDriver($this->connection);

		$cases = [
			UniqueConstraintViolationException::class => "INSERT INTO publishers (name) VALUES ('Nextras publisher')",
			ForeignKeyConstraintViolationException::class => 'UPDATE books SET author_id = 999',
			NotNullConstraintViolationException::class => 'UPDATE books SET title = NULL',
			QueryException::class => 'SELECT FROM FROM foo',
		];

		foreach ($cases as $class => $sql) {
			$syncException = Assert::exception(fn() => $driver->query($sql), $class);
			assert($syncException instanceof QueryException);

			$driver->sendQuery($sql);
			$asyncException = Assert::exception(fn() => $driver->reapQuery(), $class);
			assert($asyncException instanceof QueryException);

			Assert::same($syncException::class, $asyncException::class);
			Assert::same($syncException->getMessage(), $asyncException->getMessage());
			Assert::same($syncException->getErrorCode(), $asyncException->getErrorCode());
			Assert::same($syncException->getErrorSqlState(), $asyncException->getErrorSqlState());
			Assert::same($sql, $asyncException->getSqlQuery());

			// the driver accepts another query after the failed one
			Assert::false($driver->hasPendingQuery());
			$driver->sendQuery('SELECT 5');
			Assert::same(5, $driver->reapQuery()->fetchField());
			Assert::same(6, $driver->query('SELECT 6')->fetchField());
		}
	}


	public function testDisconnectWhilePending()
	{
		$connection = $this->createConnection();
		$driver = $this->getAsyncDriver($connection);
		$driver->sendQuery($this->getSleepSql(2));

		$start = microtime(true);
		$driver->disconnect();
		$elapsed = microtime(true) - $start;

		Assert::true($elapsed < 1.5, "Disconnect took $elapsed s.");
		Assert::false($driver->hasPendingQuery());
		Assert::false($driver->isConnected());
		Assert::exception(fn() => $driver->reapQuery(), InvalidStateException::class);

		$connection->reconnect();
		Assert::false($driver->hasPendingQuery());
		Assert::same(1, $driver->query('SELECT 1')->fetchField());
		$driver->sendQuery('SELECT 2');
		Assert::same(2, $driver->reapQuery()->fetchField());
	}


	public function testSendWhileDisconnected()
	{
		$driver = $this->getAsyncDriver($this->createConnection());
		$driver->disconnect();
		Assert::exception(fn() => $driver->sendQuery('SELECT 1'), InvalidStateException::class);
		Assert::false($driver->hasPendingQuery());
	}


	public function getAsyncDriver(Connection $connection): IAsyncQueryDriver
	{
		$connection->connect();
		$driver = $connection->getDriver();
		assert($driver instanceof IAsyncQueryDriver);
		return $driver;
	}


	private function getSleepSql(float $seconds): string
	{
		return $this->connection->getDriver() instanceof MysqliDriver
			? "SELECT SLEEP($seconds)"
			: "SELECT pg_sleep($seconds)";
	}
}


$test = new DriverAsyncTest();
$test->run();
