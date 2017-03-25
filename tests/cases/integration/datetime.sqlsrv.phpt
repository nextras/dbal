<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlsrv
 */

namespace NextrasTests\Dbal;

use DateTime;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class DateTimeSqlsrvTest extends IntegrationTestCase
{
	public function testSimple()
	{
		$connection = $this->createConnection();
		$connection->query('DROP TABLE IF EXISTS dates_write');
		$connection->query('
			CREATE TABLE dates_write (
				a datetime
			);
		');

		$connection->query('INSERT INTO dates_write VALUES (%dts)',
			new DateTime('2015-01-01 12:00:00') // simple
		);

		$result = $connection->query('SELECT * FROM dates_write');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00.000', $row->a);

		// different timezone than db
		date_default_timezone_set('Europe/Kiev');

		$result = $connection->query('SELECT * FROM dates_write');
		$result->setValueNormalization(false);

		$row = $result->fetch();
		Assert::same('2015-01-01 12:00:00.000', $row->a);
	}


	public function testDateTimeOffset()
	{
		$connection = $this->createConnection();
		$connection->query('DROP TABLE IF EXISTS dates');
		$connection->query('
			CREATE TABLE dates (
				a datetimeoffset
			);
		');

		$connection->query('INSERT INTO dates VALUES (%dt)',
			new DateTime('2015-01-01 12:00:00') // 11:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates');
		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->a);
		Assert::same('2015-01-01T12:00:00+01:00', $row->a->format('c'));


		$connection->query('DELETE FROM dates');
		$connection->query('INSERT INTO dates VALUES (%dt)',
			new DateTime('2015-01-01 13:00:00 Europe/Kiev') // 11:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates');
		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->a);
		Assert::same('2015-01-01T13:00:00+02:00', $row->a->format('c'));


		// different timezone than db
		date_default_timezone_set('Europe/London');

		$connection->query('DELETE FROM dates');
		$connection->query('INSERT INTO dates VALUES (%dt)',
			new DateTime('2015-01-01 14:00:00 Europe/Kiev') // 12:00 UTC
		);

		$result = $connection->query('SELECT * FROM dates');
		$row = $result->fetch();
		Assert::type(DateTimeImmutable::class, $row->a);
		Assert::same('2015-01-01T14:00:00+02:00', $row->a->format('c'));
	}
}


$test = new DateTimeSqlsrvTest();
$test->run();
