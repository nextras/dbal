<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use DateTime;
use Nextras\Dbal\Exception\InvalidArgumentException;
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

		$interval1 = (new DateTime('2015-01-03 12:01:01'))->diff(new DateTime('2015-01-01 09:00:01'));
		$interval2 = (new DateTime('2015-01-01 09:00:00'))->diff(new DateTime('2015-01-03 12:01:05'));

		Assert::same("-51:01:00", trim($driver->convertDateIntervalToSql($interval1), "'"));
		Assert::same("51:01:05", trim($driver->convertDateIntervalToSql($interval2), "'"));

		Assert::throws(function () use ($driver) {
			$interval = (new DateTime('2015-02-05 09:59:59'))->diff(new DateTime('2015-01-01 09:00:00'));
			$driver->convertDateIntervalToSql($interval);
		}, InvalidArgumentException::class, 'Mysql cannot store interval bigger than 839h:59m:59s.');
	}




	public function testLike()
	{
		$c = $this->connection;
		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %_like_", "A'B")->fetchField());
		Assert::truthy($c->query("SELECT 'AA''BB' LIKE %_like_", "A'B")->fetchField());

		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %_like_", "A\\B")->fetchField());
		Assert::truthy($c->query("SELECT 'AA\\\\BB' LIKE %_like_", "A\\B")->fetchField());

		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %_like_", "A%B")->fetchField());
		Assert::truthy($c->query("SELECT %raw     LIKE %_like_", "'AA%BB'", "A%B")->fetchField());

		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %_like_", "A_B")->fetchField());
		Assert::truthy($c->query("SELECT 'AA_BB'  LIKE %_like_", "A_B")->fetchField());


		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %_like", "AAAxBB")->fetchField());
		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %_like", "AxB")->fetchField());
		Assert::truthy($c->query("SELECT 'AAxBB'  LIKE %_like", "AxBB")->fetchField());

		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %like_", "AAxBBB")->fetchField());
		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %like_", "AxB")->fetchField());
		Assert::truthy($c->query("SELECT 'AAxBB'  LIKE %like_", "AAxB")->fetchField());
	}
}


$test = new DriverMysqlTest();
$test->run();
