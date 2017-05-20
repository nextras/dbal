<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlsrv
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\Sqlsrv\SqlsrvDriver;
use Nextras\Dbal\QueryException;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class ConnectionSqlsrvTest extends IntegrationTestCase
{
	public function testReconnect()
	{
		$this->connection->query('create table #temp (val int)');
		$this->connection->query('insert into #temp values (1)');
		Assert::same(1, $this->connection->query('SELECT * FROM #temp')->fetchField());
		$this->connection->reconnect();
		Assert::exception(function () {
			$this->connection->query('SELECT * FROM #temp');
		}, QueryException::class);
	}


	public function testLastInsertId()
	{
		$this->connection->query('CREATE TABLE autoi_1 (a int NOT NULL IDENTITY PRIMARY KEY)');
		$this->connection->query('CREATE TABLE autoi_2 (b int NOT NULL IDENTITY PRIMARY KEY)');

		for ($i = 1; $i < 4; $i++) {
			$this->connection->query('INSERT INTO autoi_1 DEFAULT VALUES');
			Assert::same($i, $this->connection->getLastInsertedId());
		}

		$this->connection->query('INSERT INTO autoi_2 DEFAULT VALUES');
		Assert::same(1, $this->connection->getLastInsertedId());

		$this->connection->query('CREATE TRIGGER autoi_2_ai ON autoi_2 AFTER INSERT AS INSERT INTO autoi_1 DEFAULT VALUES');
		$this->connection->query('INSERT INTO autoi_2 DEFAULT VALUES');
		Assert::same(2, $this->connection->getLastInsertedId());
	}


	public function testReconnectWithConfig()
	{
		$config = $this->connection->getConfig();
		$this->connection->connect();

		Assert::true($this->connection->getDriver()->isConnected());
		$oldDriver = $this->connection->getDriver();

		$config['driver'] = new SqlsrvDriver($config);
		$this->connection->reconnectWithConfig($config);

		$newDriver = $this->connection->getDriver();
		Assert::notSame($oldDriver, $newDriver);
	}
}


$test = new ConnectionSqlsrvTest();
$test->run();
