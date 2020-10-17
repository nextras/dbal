<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use DateInterval;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class TypesMysqlTest extends IntegrationTestCase
{
	public function testRead()
	{
		$this->connection->query("
			CREATE TEMPORARY TABLE [types_read] (
				[bit] bit(4),

				[unsigned_int] int(11) unsigned,
				[int] int(11),
				[smallint] smallint(6),
				[tinyint] tinyint(4),
				[mediumint] mediumint(9),
				[bigint] bigint(20),
				[year] year(4),

				[decimal] decimal(10,0),
				[decimal2] decimal(10,2),
				[float] float,
				[double] double,

				[time] time,

				[string] varchar(200)
			) ENGINE=InnoDB;
		");
		$this->connection->query("
			INSERT INTO [types_read] VALUES (
				b'0100',

				12,
				-12,
				1,
				1,
				1,
				1,
				2015,

				100,
				100.22,
				12.34,
				12.34,

				'32:57',

				'foo'
			)
		");

		$row = $this->connection->query('SELECT * FROM [types_read]')->fetch();
		Assert::same(4, $row->bit);

		Assert::same(12, $row->unsigned_int);
		Assert::same(-12, $row->int);
		Assert::same(1, $row->smallint);
		Assert::same(1, $row->tinyint);
		Assert::same(1, $row->mediumint);
		Assert::same(1, $row->bigint);
		Assert::same(2015, $row->year);

		Assert::same(100.0, $row->decimal);
		Assert::same(100.22, $row->decimal2);
		Assert::same(12.34, $row->float);
		Assert::same(12.34, $row->double);

		Assert::equal(new DateInterval('PT32H57M'), $row->time);

		Assert::same('foo', $row->string);
	}


	public function testWrite()
	{
		$this->connection->query("
			CREATE TEMPORARY TABLE [types_write] (
				[blob] blob,
				[json] text,
				[bool] tinyint
			) ENGINE=InnoDB;
		");

		$file = file_get_contents(__DIR__ . '/nextras.png');
		$this->connection->query('INSERT INTO [types_write] %values', [
			'blob%blob' => $file,
			'json%json' => [1, '2', true, null],
			'bool%b' => true,
		]);
		$row = $this->connection->query('SELECT * FROM [types_write]')->fetch();
		Assert::same($file, $row->blob);
		Assert::same('[1,"2",true,null]', $row->json);
		Assert::same(1, $row->bool);
	}
}


$test = new TypesMysqlTest();
$test->run();
