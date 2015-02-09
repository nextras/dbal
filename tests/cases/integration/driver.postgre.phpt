<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini postgre
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class DriverPostgreTest extends IntegrationTestCase
{

	public function testDelimite()
	{
		$driver = $this->connection->getDriver();
		$this->connection->connect();

		Assert::same('"foo"', $driver->convertToSql('foo', IDriver::TYPE_IDENTIFIER));
		Assert::same('"foo"."bar"', $driver->convertToSql('foo.bar', IDriver::TYPE_IDENTIFIER));
		Assert::same('"foo".*', $driver->convertToSql('foo.*', IDriver::TYPE_IDENTIFIER));
		Assert::same('"foo"."bar"."baz"', $driver->convertToSql('foo.bar.baz', IDriver::TYPE_IDENTIFIER));
		Assert::same('"foo"."bar".*', $driver->convertToSql('foo.bar.*', IDriver::TYPE_IDENTIFIER));
	}

}


$test = new DriverPostgreTest();
$test->run();
