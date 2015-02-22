<?php

/** @testCase */

namespace NextrasTests\Dbal;

use DateTime;
use DateTimeZone;
use Nextras\Dbal\Utils\DateTimeFactory;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class DateTimeTest extends TestCase
{

	public function testToString()
	{
		date_default_timezone_set('Europe/Prague');

		Assert::same(
			'2015-01-01T04:27:35+00:00',
			(string) DateTimeFactory::from(new DateTime('2015-01-01T05:27:35+01:00'), new DateTimeZone('Europe/London'))
		);
	}

}


$test = new DateTimeTest();
$test->run();
