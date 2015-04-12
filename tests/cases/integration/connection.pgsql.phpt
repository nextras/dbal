<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ConnectionPgsqlTest extends IntegrationTestCase
{

	public function testReconnect()
	{
		$processId = $this->connection->query('SELECT pg_backend_pid()')->fetchField();
		Assert::same($processId, $this->connection->query('SELECT pg_backend_pid()')->fetchField());
		$this->connection->reconnect();
		Assert::notSame($processId, $this->connection->query('SELECT pg_backend_pid()')->fetchField());
	}


	public function testLastInsertId()
	{
		$this->initData($this->connection);

		$this->connection->query('INSERT INTO publishers %values', ['name' => 'FOO']);
		Assert::same(2, $this->connection->getLastInsertedId("publishers_id_seq"));

		Assert::exception(function() {
			$this->connection->getLastInsertedId();
		}, 'Nextras\Dbal\InvalidArgumentException', 'PgsqlDriver require to pass sequence name for getLastInsertedId() method.');
	}

}


$test = new ConnectionPgsqlTest();
$test->run();
