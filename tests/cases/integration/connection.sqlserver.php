<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlsrv
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\Exception\QueryException;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class ConnectionSqlServerTest extends IntegrationTestCase
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
		$this->lockConnection($this->connection);
		$this->connection->query('DROP TABLE IF EXISTS autoi_1');
		$this->connection->query('DROP TABLE IF EXISTS autoi_2');

		$this->connection->query('CREATE TABLE autoi_1 (a int NOT NULL IDENTITY PRIMARY KEY)');
		$this->connection->query('CREATE TABLE autoi_2 (b int NOT NULL IDENTITY PRIMARY KEY)');

		for ($i = 1; $i < 4; $i++) {
			$this->connection->query('INSERT INTO autoi_1 DEFAULT VALUES');
			Assert::same("$i", $this->connection->getLastInsertedId());
		}

		$this->connection->query('INSERT INTO autoi_2 DEFAULT VALUES');
		Assert::same('1', $this->connection->getLastInsertedId());

		$this->connection->query('CREATE TRIGGER autoi_2_ai ON autoi_2 AFTER INSERT AS INSERT INTO autoi_1 DEFAULT VALUES');
		$this->connection->query('INSERT INTO autoi_2 DEFAULT VALUES');
		Assert::same('2', $this->connection->getLastInsertedId());
	}
}


$test = new ConnectionSqlServerTest();
$test->run();
