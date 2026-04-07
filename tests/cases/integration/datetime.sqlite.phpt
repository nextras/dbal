<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlite
 */

namespace NextrasTests\Dbal;


use DateTime;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Tester\Assert;
use function strtotime;


require_once __DIR__ . '/../../bootstrap.php';


class DateTimeSqliteTest extends IntegrationTestCase
{
	public function testWriteStorage()
	{
		$connection = $this->createConnection();
		$this->lockConnection($connection);

		$connection->query(/** @lang GenericSQL */ '
			CREATE TEMP TABLE dates_write (
				a varchar,
				b numeric
			);
		');

		$connection->query('INSERT INTO dates_write VALUES (%ldt, %dt)',
			new DateTime('2015-01-01 12:00:00'), // local
			new DateTime('2015-01-01 12:00:00')  // 11:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates_write');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00.000000', $row->a);
		Assert::same(strtotime('2015-01-01T11:00:00Z') * 1000, $row->b * 1);

		$connection->query('DELETE FROM dates_write');
		$connection->query('INSERT INTO dates_write VALUES (%ldt, %dt)',
			new DateTime('2015-01-01 12:00:00'),             // local
			new DateTime('2015-01-01 12:00:00 Europe/Kiev')  // 10:00 UTC,
		);

		$result = $connection->query('SELECT * FROM dates_write');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00.000000', $row->a);
		Assert::same(strtotime('2015-01-01T10:00:00Z') * 1000, $row->b * 1);
	}


	public function testReadStorage()
	{
		$connection = $this->createConnection();
		$this->lockConnection($connection);

		$connection->query('DROP TABLE IF EXISTS dates_read');
		$connection->query('
			CREATE TABLE dates_read (
				a dbal_local_datetime,
				b dbal_timestamp,
				c dbal_local_date
			);
		');

		$connection->query('INSERT INTO dates_read VALUES (%s, %s, %s)',
			'2015-01-01 12:00:00', // local
			'2015-01-01 12:00:00', // connection tz
			'2015-01-01'
		);

		$result = $connection->query('SELECT * FROM dates_read');

		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->a);
		Assert::type(DateTimeImmutable::class, $row->b);
		Assert::type(DateTimeImmutable::class, $row->c);
		Assert::same('2015-01-01T12:00:00+01:00', $row->a->format('c'));
		Assert::same('2015-01-01T13:00:00+01:00', $row->b->format('c'));
		Assert::same('2015-01-01T00:00:00+01:00', $row->c->format('c'));
	}


	public function testMicroseconds()
	{
		$connection = $this->createConnection();
		$this->lockConnection($connection);

		$connection->query('DROP TABLE IF EXISTS dates_micro');
		$connection->query('
			CREATE TABLE dates_micro (
				a dbal_local_datetime(6),
				b dbal_timestamp(6)
			);
		');

		$now = new DateTime();
		$connection->query('INSERT INTO dates_micro %values', [
			'a%ldt' => $now,
			'b%dt' => $now,
		]);

		$row = $connection->query('SELECT * FROM dates_micro')->fetch();
		Assert::same($now->format('u'), $row->a->format('u'));
		Assert::same(substr($now->format('u'), 0, 3) . '000', $row->b->format('u'));
	}
}


$test = new DateTimeSqliteTest();
$test->run();
