<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ConnectionTest extends IntegrationTestCase
{

	public function testPing()
	{
		Assert::false($this->connection->getDriver()->isConnected());
		$this->connection->ping();
		Assert::true($this->connection->getDriver()->isConnected());
	}


	public function testFireEvent()
	{
		$log = [];
		$this->connection->onConnect[] = function() use (& $log) { $log[] = 'connect'; };
		$this->connection->onDisconnect[] = function() use (& $log) { $log[] = 'disconnect'; };

		$this->connection->ping();
		$this->connection->reconnect();
		$this->connection->disconnect();

		Assert::same([
			'connect',
			'disconnect',
			'connect',
			'disconnect',
		], $log);
	}


	public function testMissingDriver()
	{
		Assert::exception(function() {
			$this->createConnection(['driver' => NULL]);
		}, 'Nextras\Dbal\InvalidStateException');
	}

}


$test = new ConnectionTest();
$test->run();
