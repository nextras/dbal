<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlite
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Utils\DateTimeImmutable;
use Tester\Assert;
use function strtotime;


require_once __DIR__ . '/../../bootstrap.php';


class TypesSqliteTest extends IntegrationTestCase
{
	public function testRead()
	{
		$this->connection->query("
			CREATE TEMP TABLE types_read (
				local_date dbal_local_date,
				local_datetime dbal_local_datetime,
				utc_datetime_ms dbal_timestamp,
				local_time dbal_local_time,
				integer1 tinyint,
				integer2 smallint,
				integer3 int,
				integer4 bigint,
				float1 float,
				real1 real,
				numeric1 numeric(5,2),
				numeric2 numeric(5,2),
				numeric3 numeric,
				decimal1 decimal(5,2),
				decimal2 decimal(5,2),
				decimal3 decimal,
				boolean dbal_bool
			);
		");
		$this->connection->query('INSERT INTO types_read %values', [
			'local_date' => '2017-02-22',
			'local_datetime' => '2017-02-22 16:40:00',
			'utc_datetime_ms' => strtotime('2017-02-22T16:40:00Z') * 1000,
			'local_time' => '16:40',
			'integer1' => 1,
			'integer2' => 1,
			'integer3' => 1,
			'integer4' => 1,
			'float1' => 12,
			'real1' => 12,
			'numeric1' => 12.04,
			'numeric2' => 12,
			'numeric3' => 12,
			'decimal1' => 12.04,
			'decimal2' => 12,
			'decimal3' => 12,
			'boolean' => 1,
		]);

		$result = $this->connection->query('SELECT * FROM types_read');

		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->local_date);
		Assert::type(DateTimeImmutable::class, $row->local_datetime);
		Assert::type(DateTimeImmutable::class, $row->utc_datetime_ms);
		Assert::type(DateTimeImmutable::class, $row->local_time);
		Assert::same('2017-02-22T00:00:00+01:00', $row->local_date->format('c'));
		Assert::same('2017-02-22T16:40:00+01:00', $row->local_datetime->format('c'));
		Assert::same('2017-02-22T17:40:00+01:00', $row->utc_datetime_ms->format('c'));
		Assert::same('16:40:00', $row->local_time->format('H:i:s'));

		Assert::same(1, $row->integer1);
		Assert::same(1, $row->integer2);
		Assert::same(1, $row->integer3);
		Assert::same(1, $row->integer4);

		Assert::same(12.0, $row->float1);
		Assert::same(12.0, $row->real1);

		Assert::same(12.04, $row->numeric1);
		Assert::same(12.00, $row->numeric2);
		Assert::same(12.0, $row->numeric3);

		Assert::same(12.04, $row->decimal1);
		Assert::same(12.00, $row->decimal2);
		Assert::same(12.0, $row->decimal3);

		Assert::same(true, $row->boolean);
	}
}


$test = new TypesSqliteTest();
$test->run();
