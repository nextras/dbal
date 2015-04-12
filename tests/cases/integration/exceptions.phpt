<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ExceptionsTest extends IntegrationTestCase
{

	public function testConnection()
	{
		Assert::exception(function() {
			$connection = $this->createConnection(['database' => 'unknown']);
			$connection->connect();
		}, 'Nextras\Dbal\ConnectionException');

		Assert::exception(function() {
			$connection = $this->createConnection(['username' => 'unknown']);
			$connection->connect();
		}, 'Nextras\Dbal\ConnectionException');

		Assert::exception(function() {
			$connection = $this->createConnection(['password' => 'unknown']);
			$connection->connect();
		}, 'Nextras\Dbal\ConnectionException');
	}

}


$test = new ExceptionsTest();
$test->run();
