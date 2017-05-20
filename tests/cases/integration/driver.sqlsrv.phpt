<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlsrv
 */

namespace NextrasTests\Dbal;

use DateTime;
use Nextras\Dbal\NotSupportedException;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class DriverSqlSrvTest extends IntegrationTestCase
{
	public function testDelimite()
	{
		$driver = $this->connection->getDriver();
		$this->connection->connect();

		Assert::same('[foo]', $driver->convertIdentifierToSql('foo'));
		Assert::same('[foo].[bar]', $driver->convertIdentifierToSql('foo.bar'));
		Assert::same('[foo].*', $driver->convertIdentifierToSql('foo.*'));
		Assert::same('[foo].[bar].[baz]', $driver->convertIdentifierToSql('foo.bar.baz'));
		Assert::same('[foo].[bar].*', $driver->convertIdentifierToSql('foo.bar.*'));
	}


	public function testDateInterval()
	{
		Assert::exception(function () {
			$interval1 = (new DateTime('2015-01-03 12:01:01'))->diff(new DateTime('2015-01-01 09:00:00'));
			$this->connection->getDriver()->convertDateIntervalToSql($interval1);
		}, NotSupportedException::class);
	}


	public function testLike()
	{
		$c = $this->connection;
		Assert::same(0, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %_like_ THEN 1 ELSE 0 END", "A'B")->fetchField());
		Assert::same(1, $c->query("SELECT CASE WHEN 'AA''BB' LIKE %_like_ THEN 1 ELSE 0 END", "A'B")->fetchField());

		Assert::same(0, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %_like_ THEN 1 ELSE 0 END", "A\\B")->fetchField());
		Assert::same(1, $c->query("SELECT CASE WHEN 'AA\\BB' LIKE %_like_ THEN 1 ELSE 0 END", "A\\B")->fetchField());

		Assert::same(0, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %_like_ THEN 1 ELSE 0 END", "A%B")->fetchField());
		Assert::same(1, $c->query("SELECT CASE WHEN %raw     LIKE %_like_ THEN 1 ELSE 0 END", "'AA%BB'", "A%B")->fetchField());

		Assert::same(0, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %_like_ THEN 1 ELSE 0 END", "A_B")->fetchField());
		Assert::same(1, $c->query("SELECT CASE WHEN 'AA_BB'  LIKE %_like_ THEN 1 ELSE 0 END", "A_B")->fetchField());


		Assert::same(0, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %_like THEN 1 ELSE 0 END", "AAAxBB")->fetchField());
		Assert::same(0, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %_like THEN 1 ELSE 0 END", "AxB")->fetchField());
		Assert::same(1, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %_like THEN 1 ELSE 0 END", "AxBB")->fetchField());

		Assert::same(0, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %like_ THEN 1 ELSE 0 END", "AAxBBB")->fetchField());
		Assert::same(0, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %like_ THEN 1 ELSE 0 END", "AxB")->fetchField());
		Assert::same(1, $c->query("SELECT CASE WHEN 'AAxBB'  LIKE %like_ THEN 1 ELSE 0 END", "AAxB")->fetchField());
	}
}


$test = new DriverSqlSrvTest();
$test->run();
