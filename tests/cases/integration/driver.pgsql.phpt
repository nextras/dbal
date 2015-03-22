<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
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


	public function testBoolean()
	{
		$this->connection->query("
			CREATE TEMPORARY TABLE [driver_types] (
				[is_bool] boolean
			);
		");

		$result = $this->connection->query('SELECT * FROM [driver_types] WHERE [is_bool] = %b', TRUE);
		Assert::same(0, iterator_count($result));
	}

}


$test = new DriverPostgreTest();
$test->run();
