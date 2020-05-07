<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Exception\InvalidArgumentException;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class ConnectionTest extends IntegrationTestCase
{
	public function testPing()
	{
		Assert::false($this->connection->getDriver()->isConnected());
		Assert::false($this->connection->ping());
		Assert::false($this->connection->getDriver()->isConnected());
		$this->connection->connect();
		Assert::true($this->connection->getDriver()->isConnected());
		Assert::true($this->connection->ping());
	}


	public function testFireEvent()
	{
		$logger = new TestLogger();
		$this->connection->addLogger($logger);

		$this->connection->ping();
		$this->connection->reconnect();
		$this->connection->disconnect();

		Assert::same([
			'connect',
			'disconnect',
		], $logger->logged);
	}


	public function testFireEvent2()
	{
		$logger = new TestLogger();
		$this->connection->addLogger($logger);

		$this->connection->disconnect();
		$this->connection->reconnect();
		$this->connection->connect();
		Assert::same(['connect'], $logger->logged);
	}


	public function testMissingDriver()
	{
		Assert::exception(function () {
			$this->createConnection(['driver' => null]);
		}, InvalidArgumentException::class);
	}
}


$test = new ConnectionTest();
$test->run();
