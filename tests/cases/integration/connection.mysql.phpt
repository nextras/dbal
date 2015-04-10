<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ConnectionMysqlTest extends IntegrationTestCase
{

	public function testReconnect()
	{
		$this->connection->query('SET @var := 1');
		Assert::same(1, $this->connection->query('SELECT @var')->fetchField());
		$this->connection->reconnect();
		Assert::same(NULL, $this->connection->query('SELECT @var')->fetchField());
	}


	public function testLastInsertId()
	{
		$this->initData($this->connection);

		$this->connection->query('INSERT INTO publishers %values', ['name' => 'FOO']);
		Assert::same(2, $this->connection->getLastInsertedId());
	}

}


$test = new ConnectionMysqlTest();
$test->run();
