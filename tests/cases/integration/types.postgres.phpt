<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use DateInterval;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class TypesPostgresTest extends IntegrationTestCase
{
	public function testRead()
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
			TRUE::bool,
			'16:00'::time
		");

		$row = $result->fetch();
		Assert::equal(DateInterval::createFromDateString('1 day 01:00:00'), $row->interval);
		Assert::same(4, $row->bit);
		Assert::same(4, $row->varbit);
		Assert::same(true, $row->bool);

		Assert::same(1, $row->int8);
		Assert::same(2, $row->int4);
		Assert::same(3, $row->int2);

		Assert::same(12.04, $row->numeric);
		Assert::same(12.05, $row->float4);
		Assert::same(12.06, $row->float8);

		Assert::same('foo', $row->varchar);
		Assert::same(true, $row->bool);
		Assert::type(\DateTimeImmutable::class, $row->time);
	}


	public function testWrite()
	{
		$this->connection->query("
			CREATE TEMPORARY TABLE [types_write] (
				[blob] bytea,
				[json] json,
				[jsonb] jsonb,
				[bool] boolean
			);
		");

		$file = file_get_contents(__DIR__ . '/nextras.png');
		$this->connection->query('INSERT INTO [types_write] %values', [
			'blob%blob' => $file,
			'json%json' => [1, '2', true, null],
			'jsonb%json' => [1, '2', true, null],
			'bool%b' => true,
		]);
		$row = $this->connection->query('SELECT * FROM [types_write]')->fetch();
		Assert::same($file, $row->blob);
		Assert::same('[1,"2",true,null]', $row->json);
		Assert::same('[1, "2", true, null]', $row->jsonb);
		Assert::same(true, $row->bool);
	}
}


$test = new TypesPostgresTest();
$test->run();
