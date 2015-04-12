<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use DateTime;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class DriverMysqlTest extends IntegrationTestCase
{

	public function testDelimite()
	{
		$driver = $this->connection->getDriver();

		Assert::same('`foo`', $driver->convertToSql('foo', IDriver::TYPE_IDENTIFIER));
		Assert::same('`foo`.`bar`', $driver->convertToSql('foo.bar', IDriver::TYPE_IDENTIFIER));
		Assert::same('`foo`.*', $driver->convertToSql('foo.*', IDriver::TYPE_IDENTIFIER));
		Assert::same('`foo`.`bar`.`baz`', $driver->convertToSql('foo.bar.baz', IDriver::TYPE_IDENTIFIER));
		Assert::same('`foo`.`bar`.*', $driver->convertToSql('foo.bar.*', IDriver::TYPE_IDENTIFIER));
	}


	public function testDateInterval()
	{
		$driver = $this->connection->getDriver();

		$interval1 = (new DateTime('2015-01-03 12:01:01'))->diff(new DateTime('2015-01-01 09:00:00'));
		$interval2 = (new DateTime('2015-01-01 09:00:00'))->diff(new DateTime('2015-01-03 12:01:01'));

		Assert::same('-51:01:01', $driver->convertToSql($interval1, IDriver::TYPE_DATE_INTERVAL));
		Assert::same('51:01:01', $driver->convertToSql($interval2, IDriver::TYPE_DATE_INTERVAL));

		Assert::throws(function() use ($driver) {
			$interval = (new DateTime('2015-02-05 09:59:59'))->diff(new DateTime('2015-01-01 09:00:00'));
			$driver->convertToSql($interval, IDriver::TYPE_DATE_INTERVAL);
		}, 'Nextras\Dbal\InvalidArgumentException');
	}

}


$test = new DriverMysqlTest();
$test->run();
