<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlite
 */

namespace NextrasTests\Dbal;


use DateTime;
use Nextras\Dbal\Exception\NotSupportedException;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class PlatformFormatSqlServerTest extends IntegrationTestCase
{
	public function testDelimite()
	{
		$platform = $this->connection->getPlatform();
		$this->connection->connect();

		Assert::same('[foo]', $platform->formatIdentifier('foo'));
		Assert::same('[foo].[bar]', $platform->formatIdentifier('foo.bar'));
		Assert::same('[foo].[bar].[baz]', $platform->formatIdentifier('foo.bar.baz'));
	}


	public function testDateInterval()
	{
		Assert::exception(function () {
			$interval1 = (new DateTime('2015-01-03 12:01:01'))->diff(new DateTime('2015-01-01 09:00:00'));
			$this->connection->getPlatform()->formatDateInterval($interval1);
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
		Assert::same(1, $c->query("SELECT CASE WHEN %raw     LIKE %_like_ THEN 1 ELSE 0 END", "'AA%BB'", "A%B")
			->fetchField());

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


$test = new PlatformFormatSqlServerTest();
$test->run();
