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


class PlatformFormatSqliteTest extends IntegrationTestCase
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
		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %_like_", "A'B")->fetchField());
		Assert::truthy($c->query("SELECT 'AA''BB' LIKE %_like_", "A'B")->fetchField());

		Assert::falsey($c->query("SELECT 'AAxBB'  LIKE %_like_", "A\\B")->fetchField());
		Assert::truthy($c->query("SELECT 'AA\\BB' LIKE %_like_", "A\\B")->fetchField());

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


$test = new PlatformFormatSqliteTest();
$test->run();
