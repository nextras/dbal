<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlite
 */

namespace NextrasTests\Dbal;


use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class ConnectionSqliteTest extends IntegrationTestCase
{
	public function testReconnect()
	{
		$tableName = 'test_tmp';
		$this->connection->query('CREATE TEMP TABLE %table (id INT PRIMARY KEY)', $tableName);
		Assert::same(
			1,
			$this->connection->query(
				"SELECT COUNT(*) FROM sqlite_temp_master WHERE type = 'table' AND name = %s",
				$tableName
			)->fetchField()
		);

		$this->connection->reconnect();

		Assert::same(
			0,
			$this->connection->query(
				"SELECT COUNT(*) FROM sqlite_temp_master WHERE type = 'table' AND name = %s",
				$tableName
			)->fetchField()
		);
	}


	public function testLastInsertId()
	{
		$this->initData($this->connection);

		$this->connection->query('INSERT INTO publishers %values', ['name' => 'FOO']);
		Assert::same(2, $this->connection->getLastInsertedId());
	}
}


$test = new ConnectionSqliteTest();
$test->run();
