<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Utils\DateTime;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class DateTimeTest extends TestCase
{

	public function testToString()
	{
		date_default_timezone_set('Europe/Prague');

		Assert::same(
			'2015-01-01T05:27:35+01:00',
			(string) new DateTime('2015-01-01T05:27:35+01:00')
		);
	}

}


$test = new DateTimeTest();
$test->run();
