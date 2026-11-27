<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;


use DateInterval;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\IAsyncQueryDriver;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../bootstrap.php';


class DriverAsyncMysqlTest extends IntegrationTestCase
{
	public function setUp()
	{
		parent::setUp();
		$driver = $this->connection->getDriver();
		if (!$driver instanceof IAsyncQueryDriver) {
			Environment::skip('Driver does not implement IAsyncQueryDriver.');
		}
		if (!$driver->isAsyncQuerySupported()) {
			Environment::skip('Async queries are not supported by this PHP build (mysqlnd is required).');
		}
	}


	public function testTypesAreIdenticalToQuery()
	{
		$driver = $this->getAsyncDriver();
		$driver->query('
			CREATE TEMPORARY TABLE `async_types_read` (
				`bit` bit(4),
				`unsigned_int` int(11) unsigned,
				`int` int(11),
				`smallint` smallint(6),
				`tinyint` tinyint(4),
				`mediumint` mediumint(9),
				`bigint` bigint(20),
				`year` year(4),
				`decimal` decimal(10,0),
				`decimal2` decimal(10,2),
				`float` float,
				`double` double,
				`time` time,
				`string` varchar(200),
				`null` int
			) ENGINE=InnoDB
		');
		$driver->query("
			INSERT INTO `async_types_read` VALUES (
				b'0100', 12, -12, 1, 1, 1, 1, 2015, 100, 100.22, 12.34, 12.34, '32:57', 'foo', NULL
			)
		");

		$sql = 'SELECT * FROM `async_types_read`';
		$syncRows = $driver->query($sql)->fetchAll();
		$driver->sendQuery($sql);
		$asyncRows = $driver->reapQuery()->fetchAll();

		Assert::equal($syncRows, $asyncRows);
		Assert::count(1, $asyncRows);
		$row = $asyncRows[0];
		Assert::same(4, $row->bit);
		Assert::same(12, $row->unsigned_int);
		Assert::same(-12, $row->int);
		Assert::same(1, $row->smallint);
		Assert::same(1, $row->tinyint);
		Assert::same(1, $row->mediumint);
		Assert::same(1, $row->bigint);
		Assert::same(2015, $row->year);
		Assert::same('100', $row->decimal);
		Assert::same('100.22', $row->decimal2);
		Assert::same(12.34, $row->float);
		Assert::same(12.34, $row->double);
		Assert::equal(new DateInterval('PT32H57M'), $row->time);
		Assert::same('foo', $row->string);
		Assert::null($row->null);
	}


	public function testCallWithMultipleResultSets()
	{
		$driver = $this->getAsyncDriver();
		$driver->query('DROP PROCEDURE IF EXISTS async_two_result_sets');
		$driver->query('CREATE PROCEDURE async_two_result_sets() BEGIN SELECT 1 AS a; SELECT 2 AS b; END');

		try {
			$driver->sendQuery('CALL async_two_result_sets()');
			$row = $driver->reapQuery()->fetch();
			Assert::same(1, $row->a);
			Assert::false($driver->hasPendingQuery());

			// the connection is usable again, both synchronously and asynchronously
			Assert::same(3, $driver->query('SELECT 3')->fetchField());
			$driver->sendQuery('SELECT 4');
			Assert::same(4, $driver->reapQuery()->fetchField());
		} finally {
			$driver->query('DROP PROCEDURE IF EXISTS async_two_result_sets');
		}
	}


	public function testCallFailingInLaterStatement()
	{
		$driver = $this->getAsyncDriver();
		$driver->query('DROP PROCEDURE IF EXISTS async_failing_later');
		$driver->query('CREATE PROCEDURE async_failing_later() BEGIN SELECT 1 AS a; SELECT * FROM async_missing_table; END');

		try {
			$driver->sendQuery('CALL async_failing_later()');
			$e = Assert::exception(fn() => $driver->reapQuery(), QueryException::class);
			assert($e instanceof QueryException);
			Assert::same(1146, $e->getErrorCode());
			Assert::same('CALL async_failing_later()', $e->getSqlQuery());
			Assert::false($driver->hasPendingQuery());
			Assert::same(3, $driver->query('SELECT 3')->fetchField());
		} finally {
			$driver->query('DROP PROCEDURE IF EXISTS async_failing_later');
		}
	}


	private function getAsyncDriver(): IAsyncQueryDriver
	{
		$this->connection->connect();
		$driver = $this->connection->getDriver();
		assert($driver instanceof IAsyncQueryDriver);
		return $driver;
	}
}


$test = new DriverAsyncMysqlTest();
$test->run();
