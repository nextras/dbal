<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

require_once __DIR__ . '/../../bootstrap.php';


class QueryBuilderJoinsTest extends QueryBuilderTestCase
{
	public function testBasics()
	{
		$this->assertBuilder(
			[
				'SELECT * FROM one AS [o] ' .
				'LEFT JOIN two AS [t] ON (o.userId = t.userId) ' .
				'LEFT JOIN %table ON (%table.id = tbl.t1_id) ' .
				'LEFT JOIN %table ON (%table.id = tbl.t2_id) ' .
				'INNER JOIN three AS [th] ON (t.userId = th.userId) ' .
				'RIGHT JOIN four AS [f] ON (th.userId = f.userId)',
				't1',
				't1',
				't2',
				't2'
			],
			$this->builder()
				->from('one', 'o')
				->select('*')
				->joinLeft('two AS [t]', 'o.userId = t.userId')
				->joinLeft('%table', '%table.id = tbl.t1_id', 't1', 't1')
				->joinLeft('%table', '%table.id = tbl.t2_id', 't2', 't2')
				->joinInner('three AS [th]', 't.userId = th.userId')
				->joinRight('four AS [f]', 'th.userId = f.userId')
		);
	}
}


$test = new QueryBuilderJoinsTest();
$test->run();
