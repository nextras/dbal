<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use DateTime;
use Nextras\Dbal\InvalidArgumentException;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class DriverMysqlTest extends IntegrationTestCase
{

	public function testDelimite()
	{
		$driver = $this->connection->getDriver();

		Assert::same('`foo`', $driver->convertIdentifierToSql('foo'));
		Assert::same('`foo`.`bar`', $driver->convertIdentifierToSql('foo.bar'));
		Assert::same('`foo`.*', $driver->convertIdentifierToSql('foo.*'));
		Assert::same('`foo`.`bar`.`baz`', $driver->convertIdentifierToSql('foo.bar.baz'));
		Assert::same('`foo`.`bar`.*', $driver->convertIdentifierToSql('foo.bar.*'));
	}


	public function testDateInterval()
	{
		$driver = $this->connection->getDriver();

		$interval1 = (new DateTime('2015-01-03 12:01:01'))->diff(new DateTime('2015-01-01 09:00:00'));
		$interval2 = (new DateTime('2015-01-01 09:00:00'))->diff(new DateTime('2015-01-03 12:01:01'));

		Assert::same('-51:01:01', $driver->convertDateIntervalToSql($interval1));
		Assert::same('51:01:01', $driver->convertDateIntervalToSql($interval2));

		Assert::throws(function() use ($driver) {
			$interval = (new DateTime('2015-02-05 09:59:59'))->diff(new DateTime('2015-01-01 09:00:00'));
			$driver->convertDateIntervalToSql($interval);
		}, InvalidArgumentException::class);
	}

}


$test = new DriverMysqlTest();
$test->run();
