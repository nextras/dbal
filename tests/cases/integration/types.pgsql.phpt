<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use DateInterval;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class TypesPostgreTest extends IntegrationTestCase
{

	public function testBasics()
	{
		$result = $this->connection->query("
			SELECT
			-- driver specific
			'1 day 01:00:00'::interval,
			'0100'::bit(4),
			'100'::varbit,
			'YES'::bool,

			-- int
			'1'::int8,
			'2'::int4,
			'3'::int2,

			-- float
			'12.04'::numeric,
			'12.05'::float4,
			'12.06'::float8,

			'foo'::varchar(200),
			TRUE::bool
		");

		$row = $result->fetch();
		Assert::equal(DateInterval::createFromDateString('1 day 01:00:00'), $row->interval);
		Assert::same(4, $row->bit);
		Assert::same(4, $row->varbit);
		Assert::same(TRUE, $row->bool);

		Assert::same(1, $row->int8);
		Assert::same(2, $row->int4);
		Assert::same(3, $row->int2);

		Assert::same(12.04, $row->numeric);
		Assert::same(12.05, $row->float4);
		Assert::same(12.06, $row->float8);

		Assert::same('foo', $row->varchar);
		Assert::same(TRUE, $row->bool);
	}

}


$test = new TypesPostgreTest();
$test->run();
