<?php

/** @testCase */

namespace NextrasTests\Dbal;

use DateTime;
use DateTimeZone;
use Nextras\Dbal\Utils\DateTimeFactory;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class DateTimeFactoryTest extends TestCase
{

	public function testFrom()
	{
		date_default_timezone_set('Europe/Prague');
		Assert::same(
			'2015-01-01T12:00:00+01:00',
			DateTimeFactory::from(strtotime('2015-01-01 12:00:00'))->format('c')
		);

		Assert::same(
			'2015-01-01T12:00:00+01:00',
			DateTimeFactory::from((string) strtotime('2015-01-01 12:00:00'))->format('c')
		);

		Assert::same(
			'2015-01-01T12:27:35+07:00',
			DateTimeFactory::from('2015-01-01T12:27:35+0700')->format('c')
		);

		Assert::same(
			'2015-01-01T05:27:35+00:00',
			DateTimeFactory::from('2015-01-01T12:27:35+0700', new DateTimeZone('Europe/London'))->format('c')
		);

		Assert::same(
			'2015-01-01T04:27:35+00:00',
			DateTimeFactory::from(new DateTime('2015-01-01T05:27:35+01:00'), new DateTimeZone('Europe/London'))->format('c')
		);
	}

}


$test = new DateTimeFactoryTest();
$test->run();
