<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\InvalidStateException;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class QueryBuilderJoinsTest extends QueryBuilderTestCase
{
	public function testBasics()
	{
		$this->assertBuilder(
			[
				'SELECT * FROM one AS [o] ' .
				'LEFT JOIN two AS [t] ON (o.userId = t.userId) ' .
				'INNER JOIN three AS [th] ON (t.userId = th.userId) ' .
				'RIGHT JOIN four AS [f] ON (th.userId = f.userId)'
			],
			$this->builder()
				->from('one', 'o')
				->select('*')
				->joinLeft('two AS [t]', 'o.userId = t.userId')
				->joinInner('three AS [th]', 't.userId = th.userId')
				->joinRight('four AS [f]', 'th.userId = f.userId')
		);
	}
}


$test = new QueryBuilderJoinsTest();
$test->run();
