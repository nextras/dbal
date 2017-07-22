<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\Mysqli\MysqliDriver;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class ConnectionMysqlTest extends IntegrationTestCase
{
	public function testReconnect()
	{
		$this->connection->query('SET @var := 1');
		Assert::same(1, $this->connection->query('SELECT @var')->fetchField());
		$this->connection->reconnect();
		Assert::same(null, $this->connection->query('SELECT @var')->fetchField());
	}


	public function testLastInsertId()
	{
		$this->initData($this->connection);

		$this->connection->query('INSERT INTO publishers %values', ['name' => 'FOO']);
		Assert::same(2, $this->connection->getLastInsertedId());
	}


	public function testReconnectWithConfig()
	{
		$config = $this->connection->getConfig();
		$this->connection->connect();

		Assert::true($this->connection->getDriver()->isConnected());
		$oldDriver = $this->connection->getDriver();

		$config['driver'] = new MysqliDriver();
		$this->connection->reconnectWithConfig($config);

		$newDriver = $this->connection->getDriver();
		Assert::notSame($oldDriver, $newDriver);
	}
}


$test = new ConnectionMysqlTest();
$test->run();
