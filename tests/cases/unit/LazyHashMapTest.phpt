<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\LazyHashMap;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class LazyHashMapTest extends TestCase
{

	public function testBasic()
	{
		$counter = 0;
		$map = new LazyHashMap(function($key) use (&$counter) {
			$counter++;
			return strtoupper($key);
		});

		Assert::same('A', $map->a);
		Assert::same('A', $map->a);
		Assert::same('B', $map->b);
		Assert::same('A', $map->a);
		Assert::same('B', $map->b);
		Assert::same(2, $counter);
	}

}

$test = new LazyHashMapTest();
$test->run();
