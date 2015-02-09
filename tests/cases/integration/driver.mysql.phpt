<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IDriver;
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

}


$test = new DriverMysqlTest();
$test->run();
