<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use DateTime;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class DateTimeMysqlTest extends IntegrationTestCase
{
	public function testWriteStorageSameTZ()
	{
		$connection = $this->createConnection();
		$connection->query('DROP TABLE IF EXISTS dates_write');
		$connection->query('
			CREATE TABLE dates_write (
				a datetime,
				b timestamp,
				c date
			);
		');

		$connection->query('INSERT INTO dates_write VALUES (%ldt, %dt, %ldt)',
			new DateTime('2015-01-01 12:00:00'), // local
			new DateTime('2015-01-01 12:00:00'), // 11:00 UTC
			new DateTime('2015-01-01 00:00:00')  // local
		);

		$result = $connection->query('SELECT * FROM dates_write');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00', $row->a);
		Assert::same('2015-01-01 12:00:00', $row->b);
		Assert::same('2015-01-01', $row->c);

		$connection->query('DELETE FROM dates_write');
		$connection->query('INSERT INTO dates_write VALUES (%ldt, %dt, %ldt)',
			new DateTime('2015-01-01 12:00:00'),             // local
			new DateTime('2015-01-01 12:00:00 Europe/Kiev'), // 10:00 UTC,
			new DateTime('2015-01-01 12:13:14')              // local
		);

		$result = $connection->query('SELECT * FROM dates_write');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00', $row->a);
		Assert::same('2015-01-01 11:00:00', $row->b);
		Assert::same('2015-01-01', $row->c);
	}


	public function testWriteStorageDiffTZ()
	{
		$connection = $this->createConnection([
			'connectionTz' => 'Europe/Kiev',
		]);
		$connection->query('DROP TABLE IF EXISTS dates_write2');
		$connection->query('
			CREATE TABLE dates_write2 (
				a datetime,
				b timestamp,
				c date
			);
		');

		$connection->query('INSERT INTO dates_write2 VALUES (%ldt, %dt, %ldt)',
			new \DateTimeImmutable('2015-01-01 12:00:00'), // local
			new \DateTimeImmutable('2015-01-01 12:00:00'), // 11:00 UTC
			new \DateTimeImmutable('2015-01-01 00:00:00')  // local
		);

		$result = $connection->query('SELECT * FROM dates_write2');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00', $row->a);
		Assert::same('2015-01-01 13:00:00', $row->b);
		Assert::same('2015-01-01', $row->c);

		$connection->query('DELETE FROM dates_write2');
		$connection->query('INSERT INTO dates_write2 VALUES (%ldt, %dt, %ldt)',
			new \DateTimeImmutable('2015-01-01 12:00:00'),             // local
			new \DateTimeImmutable('2015-01-01 12:00:00 Europe/Kiev'), // 10:00 UTC
			new \DateTimeImmutable('2015-01-01 12:13:14')              // local
		);

		$result = $connection->query('SELECT * FROM dates_write2');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00', $row->a);
		Assert::same('2015-01-01 12:00:00', $row->b);
		Assert::same('2015-01-01', $row->c);
	}


	public function testReadStorageSameTZ()
	{
		$connection = $this->createConnection();
		$connection->query('DROP TABLE IF EXISTS dates_read');
		$connection->query('
			CREATE TABLE dates_read (
				a datetime,
				b timestamp,
				c date
			);
		');

		$connection->query('INSERT INTO dates_read VALUES (%s, %s, %s)',
			'2015-01-01 12:00:00', // local
			'2015-01-01 12:00:00', // 11:00 UTC
			'2015-01-01'
		);

		$result = $connection->query('SELECT * FROM dates_read');

		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->a);
		Assert::type(DateTimeImmutable::class, $row->b);
		Assert::type(DateTimeImmutable::class, $row->c);
		Assert::same('2015-01-01T12:00:00+01:00', $row->a->format('c'));
		Assert::same('2015-01-01T12:00:00+01:00', $row->b->format('c'));
		Assert::same('2015-01-01T00:00:00+01:00', $row->c->format('c'));
	}


	public function testReadStorageDiffTZ()
	{
		$connection = $this->createConnection([
			'connectionTz' => 'Europe/Kiev',
		]);
		$connection->query('DROP TABLE IF EXISTS dates_read2');
		$connection->query('
			CREATE TABLE dates_read2 (
				a datetime,
				b timestamp,
				c date
			);
		');

		$connection->query('INSERT INTO dates_read2 VALUES (%s, %s, %s)',
			'2015-01-01 12:00:00', // local
			'2015-01-01 12:00:00', // 10:00 UTC
			'2015-01-01'
		);

		$result = $connection->query('SELECT * FROM dates_read2');

		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->a);
		Assert::type(DateTimeImmutable::class, $row->b);
		Assert::type(DateTimeImmutable::class, $row->c);
		Assert::same('2015-01-01T12:00:00+01:00', $row->a->format('c'));
		Assert::same('2015-01-01T11:00:00+01:00', $row->b->format('c'));
		Assert::same('2015-01-01T00:00:00+01:00', $row->c->format('c'));
	}


	public function testMicroseconds()
	{
		$connection = $this->createConnection();
		$connection->query('
			CREATE TABLE dates_micro (
				a datetime(6),
				b timestamp(6)
			);
		');

		$now = new DateTime();
		$connection->query('INSERT INTO dates_micro %values', [
			'a%ldt' => $now,
			'b%dt' => $now,
		]);

		$row = $connection->query('SELECT * FROM dates_micro')->fetch();
		Assert::same($now->format('u'), $row->a->format('u'));
		Assert::same($now->format('u'), $row->b->format('u'));
	}
}


$test = new DateTimeMysqlTest();
$test->run();
