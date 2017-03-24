<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlsrv
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Utils\DateTimeImmutable;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class TypesSqlsrvTest extends IntegrationTestCase
{
	public function testRead()
	{
		$result = $this->connection->query("
			SELECT
			-- datetimes
			CAST('2017-02-22' AS date) as dt1,
			CAST('2017-02-22 16:40:00' AS datetime) as dt2,
			CAST('2017-02-22 16:40:00.003' AS datetime2) as dt3,
			CAST('2017-02-22 16:40:00' AS datetimeoffset) as dt4,
			CAST('2017-02-22 16:40:00' AS smalldatetime) as dt5,
			CAST('16:40' AS time) as dt6,

			-- int
			CAST('1' AS tinyint) AS integer1,
			CAST('1' AS smallint) AS integer2,
			CAST('1' AS int) AS integer3,
			CAST('1' AS bigint) AS integer4,

			-- float
			CAST('12' as float(2)) AS float1,
			CAST('12' as real) AS real1,

			CAST('12.04' as numeric(5,2)) AS numeric1,
			CAST('12' as numeric(5,2)) AS numeric2,
			CAST('12' as numeric) AS numeric3,

			CAST('12.04' as decimal(5,2)) AS decimal1,
			CAST('12' as decimal(5,2)) AS decimal2,
			CAST('12' as decimal) AS decimal3,

			CAST('12' as money) AS money1,
			CAST('12' as smallmoney) AS smallmoney1,

			-- boolean
			CAST(1 as bit) as boolean
		");

		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->dt1);
		Assert::type(DateTimeImmutable::class, $row->dt2);
		Assert::type(DateTimeImmutable::class, $row->dt3);
		Assert::type(DateTimeImmutable::class, $row->dt4);
		Assert::type(DateTimeImmutable::class, $row->dt5);
		Assert::type(DateTimeImmutable::class, $row->dt6);

		Assert::same(1, $row->integer1);
		Assert::same(1, $row->integer2);
		Assert::same(1, $row->integer3);
		Assert::same(1, $row->integer4);

		Assert::same(12.0, $row->float1);
		Assert::same(12.0, $row->real1);

		Assert::same(12.04, $row->numeric1);
		Assert::same(12.00, $row->numeric2);
		Assert::same(12, $row->numeric3);

		Assert::same(12.00, $row->money1);
		Assert::same(12.00, $row->smallmoney1);

		Assert::same(true, $row->boolean);
	}


	public function testWrite()
	{
		$this->connection->query("
			CREATE TABLE [types_write] (
				[blob] varbinary(1000),
				[json] varchar(500),
			);
		");

		$file = file_get_contents(__DIR__ . '/nextras.png');
		$this->connection->query('INSERT INTO [types_write] %values',
			[
				'blob%blob' => $file,
				'json%json' => [1, '2', true, null],
			]);
		$row = $this->connection->query('SELECT * FROM [types_write]')->fetch();
		Assert::same($file, $row->blob);
		Assert::same('[1,"2",true,null]', $row->json);
	}
}


$test = new TypesSqlsrvTest();
$test->run();
