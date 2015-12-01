<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use DateTime;
use Nextras\Dbal\Drivers\IDriver;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class DriverPostgreTest extends IntegrationTestCase
{

	public function testDelimite()
	{
		$driver = $this->connection->getDriver();
		$this->connection->connect();

		Assert::same('"foo"', $driver->convertIdentifierToSql('foo'));
		Assert::same('"foo"."bar"', $driver->convertIdentifierToSql('foo.bar'));
		Assert::same('"foo".*', $driver->convertIdentifierToSql('foo.*'));
		Assert::same('"foo"."bar"."baz"', $driver->convertIdentifierToSql('foo.bar.baz'));
		Assert::same('"foo"."bar".*', $driver->convertIdentifierToSql('foo.bar.*'));
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


	public function testDateInterval()
	{
		$driver = $this->connection->getDriver();

		$interval1 = (new DateTime('2015-01-03 12:01:01'))->diff(new DateTime('2015-01-01 09:00:00'));
		$interval2 = (new DateTime('2015-01-01 09:00:00'))->diff(new DateTime('2015-01-03 12:01:01'));

		Assert::same('P0Y0M2DT3H1M1S', $driver->convertDateIntervalToSql($interval1));
		Assert::same('P0Y0M2DT3H1M1S', $driver->convertDateIntervalToSql($interval2));
	}

}


$test = new DriverPostgreTest();
$test->run();
