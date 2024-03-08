<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

require_once __DIR__ . '/../../bootstrap.php';


class QueryBuilderFromTest extends QueryBuilderTestCase
{
	public function testRepeatedCall()
	{
		$this->assertBuilder(
			['SELECT * FROM foo'],
			$this->builder()
				->from('%table', 'alias', 'tableName')
				->from('foo')
		);
	}
}


$test = new QueryBuilderFromTest();
$test->run();
