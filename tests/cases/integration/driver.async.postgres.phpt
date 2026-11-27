<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\IAsyncQueryDriver;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../bootstrap.php';


class DriverAsyncPostgresTest extends IntegrationTestCase
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


	public function testTypesAreIdenticalToQuery()
	{
		$driver = $this->getAsyncDriver();
		$sql = "
			SELECT
			'1 day 1 hour'::interval,
			'0100'::bit(4),
			'100'::varbit,
			'YES'::bool,
			'1'::int8,
			'2'::int4,
			'3'::int2,
			'12.04'::numeric,
			'12.05'::float4,
			'12.06'::float8,
			'foo'::varchar(200),
			'16:00'::time,
			NULL::int AS null
		";

		$syncRows = $driver->query($sql)->fetchAll();
		$driver->sendQuery($sql);
		$asyncRows = $driver->reapQuery()->fetchAll();

		Assert::equal($syncRows, $asyncRows);
		Assert::count(1, $asyncRows);
		$row = $asyncRows[0];
		Assert::equal(1, $row->interval->d);
		Assert::equal(1, $row->interval->h);
		Assert::same(4, $row->bit);
		Assert::same(4, $row->varbit);
		Assert::same(true, $row->bool);
		Assert::same(1, $row->int8);
		Assert::same(2, $row->int4);
		Assert::same(3, $row->int2);
		Assert::same('12.04', $row->numeric);
		Assert::same(12.05, $row->float4);
		Assert::same(12.06, $row->float8);
		Assert::same('foo', $row->varchar);
		Assert::type(\DateTimeImmutable::class, $row->time);
		Assert::null($row->null);
	}


	public function testMultipleStatements()
	{
		$driver = $this->getAsyncDriver();
		$driver->sendQuery('SELECT 1 AS a; SELECT 2 AS b');
		$row = $driver->reapQuery()->fetch();
		Assert::same(1, $row->a);
		Assert::false($driver->hasPendingQuery());

		// the connection is usable again, both synchronously and asynchronously
		Assert::same(3, $driver->query('SELECT 3')->fetchField());
		$driver->sendQuery('SELECT 4');
		Assert::same(4, $driver->reapQuery()->fetchField());
	}


	public function testMultipleStatementsFailingInLaterStatement()
	{
		$driver = $this->getAsyncDriver();
		$sql = 'SELECT 1 AS a; SELECT FROM FROM foo';
		$driver->sendQuery($sql);
		$e = Assert::exception(fn() => $driver->reapQuery(), QueryException::class);
		assert($e instanceof QueryException);
		Assert::same('42601', $e->getErrorSqlState());
		Assert::same($sql, $e->getSqlQuery());
		Assert::false($driver->hasPendingQuery());
		Assert::same(3, $driver->query('SELECT 3')->fetchField());
	}


	public function testCopyDoesNotHang()
	{
		$driver = $this->getAsyncDriver();
		$driver->query('CREATE TEMPORARY TABLE async_copy (id int)');

		$cases = [
			'COPY (SELECT 1) TO STDOUT',
			'COPY async_copy FROM STDIN',
			'SELECT 1; COPY (SELECT 1) TO STDOUT; SELECT 2',
		];
		foreach ($cases as $sql) {
			$driver->sendQuery($sql);
			$e = Assert::exception(fn() => $driver->reapQuery(), DriverException::class);
			Assert::contains($sql, $e->getMessage());
			Assert::false($driver->hasPendingQuery());

			// the connection is usable right away
			Assert::same(1, $driver->query('SELECT 1')->fetchField());
			$driver->sendQuery('SELECT 2');
			Assert::same(2, $driver->reapQuery()->fetchField());
		}
		Assert::same(0, $driver->query('SELECT COUNT(*) FROM async_copy')->fetchField());

		// and after a reconnect
		$this->connection->reconnect();
		$driver->sendQuery('COPY (SELECT 1) TO STDOUT');
		Assert::exception(fn() => $driver->reapQuery(), DriverException::class);
		Assert::same(3, $driver->query('SELECT 3')->fetchField());
	}


	public function testDisconnectWhileCopyIsPending()
	{
		$connection = $this->createConnection();
		$connection->connect();
		$driver = $connection->getDriver();
		assert($driver instanceof IAsyncQueryDriver);

		$driver->sendQuery('COPY (SELECT 1) TO STDOUT');
		usleep(200_000); // let the COPY response arrive
		$driver->disconnect();
		Assert::false($driver->hasPendingQuery());
		Assert::false($driver->isConnected());

		$connection->reconnect();
		Assert::same(1, $driver->query('SELECT 1')->fetchField());
	}


	private function getAsyncDriver(): IAsyncQueryDriver
	{
		$this->connection->connect();
		$driver = $this->connection->getDriver();
		assert($driver instanceof IAsyncQueryDriver);
		return $driver;
	}
}


$test = new DriverAsyncPostgresTest();
$test->run();
