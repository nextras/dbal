<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\Pgsql\PgsqlDriver;
use Nextras\Dbal\InvalidArgumentException;
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
		}, InvalidArgumentException::class, 'PgsqlDriver require to pass sequence name for getLastInsertedId() method.');
	}


	public function testReconnectWithConfig()
	{
		$config = $this->connection->getConfig();
		$this->connection->connect();

		Assert::true($this->connection->getDriver()->isConnected());
		$oldDriver = $this->connection->getDriver();

		$config['driver'] = new PgsqlDriver($config);
		$this->connection->reconnectWithConfig($config);

		$newDriver = $this->connection->getDriver();
		Assert::notSame($oldDriver, $newDriver);
	}

}


$test = new ConnectionPgsqlTest();
$test->run();
