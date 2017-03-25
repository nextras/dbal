<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use DateTime;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class DateTimePostgreTest extends IntegrationTestCase
{
	public function testWriteStorageSameTZ()
	{
		$connection = $this->createConnection();
		$connection->query('DROP TABLE IF EXISTS dates_write');
		$connection->query('
			CREATE TABLE dates_write (
				a timestamp,
				b timestamptz
			);
		');

		$connection->query('INSERT INTO dates_write VALUES (%dts, %dt)',
			new DateTime('2015-01-01 12:00:00'), // simple
			new DateTime('2015-01-01 12:00:00')  // 11:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates_write');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00', $row->a);
		Assert::same('2015-01-01 12:00:00+01', $row->b);

		$connection->query('DELETE FROM dates_write');
		$connection->query('INSERT INTO dates_write VALUES (%dts, %dt)',
			new DateTime('2015-01-01 12:00:00'),             // simple
			new DateTime('2015-01-01 12:00:00 Europe/Kiev')  // 10:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates_write');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00', $row->a);
		Assert::same('2015-01-01 11:00:00+01', $row->b);
	}


	public function testWriteStorageDiffTZ()
	{
		$connection = $this->createConnection([
			'connectionTz' => 'Europe/Kiev',
		]);

		$connection->query('DROP TABLE IF EXISTS dates_write2');
		$connection->query('
			CREATE TABLE dates_write2 (
				a timestamp,
				b timestamptz
			);
		');

		$connection->query('INSERT INTO dates_write2 VALUES (%dts, %dt)',
			new \DateTimeImmutable('2015-01-01 12:00:00'), // simple
			new \DateTimeImmutable('2015-01-01 12:00:00')  // 11:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates_write2');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00', $row->a);
		Assert::same('2015-01-01 13:00:00+02', $row->b);

		$connection->query('DELETE FROM dates_write2');
		$connection->query('INSERT INTO dates_write2 VALUES (%dts, %dt)',
			new \DateTimeImmutable('2015-01-01 12:00:00'),             // 11:00 UTC
			new \DateTimeImmutable('2015-01-01 12:00:00 Europe/Kiev')  // 10:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates_write2');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00', $row->a);
		Assert::same('2015-01-01 12:00:00+02', $row->b);
	}


	public function testReadStorageSameTZ()
	{
		$connection = $this->createConnection();
		$connection->query('DROP TABLE IF EXISTS dates_read');
		$connection->query('
			CREATE TABLE dates_read (
				a timestamp,
				b timestamptz
			);
		');

		$connection->query('INSERT INTO dates_read VALUES (%s, %s)',
			'2015-01-01 12:00:00', // simple
			'2015-01-01 12:00:00'  // 11:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates_read');

		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->a);
		Assert::same('2015-01-01T12:00:00+01:00', $row->a->format('c'));
		Assert::same('2015-01-01T12:00:00+01:00', $row->b->format('c'));
	}


	public function testReadStorageDiffTZ()
	{
		$connection = $this->createConnection([
			'connectionTz' => 'Europe/Kiev',
		]);
		$connection->query('DROP TABLE IF EXISTS dates_read2');
		$connection->query('
			CREATE TABLE dates_read2 (
				a timestamp,
				b timestamptz
			);
		');

		$connection->query('INSERT INTO dates_read2 VALUES (%s, %s)',
			'2015-01-01 12:00:00', // simple
			'2015-01-01 12:00:00'  // 10:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates_read2');

		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->a);
		Assert::same('2015-01-01T12:00:00+01:00', $row->a->format('c'));
		Assert::same('2015-01-01T11:00:00+01:00', $row->b->format('c'));
	}


	public function testUsageWithInterval()
	{
		$connection = $this->createConnection();

		Assert::noError(function () use ($connection) {
			$connection->query('SELECT Now() <= %dt + (INTERVAL \'2 DAYS\')', new DateTime());
		});
	}
}


$test = new DateTimePostgreTest();
$test->run();
