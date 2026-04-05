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
				->addLeftJoin('two AS [t]', 'o.userId = t.userId')
				->addLeftJoin('%table', '%table.id = tbl.t1_id', 't1', 't1')
				->addLeftJoin('%table', '%table.id = tbl.t2_id', 't2', 't2')
				->addInnerJoin('three AS [th]', 't.userId = th.userId')
				->addRightJoin('four AS [f]', 'th.userId = f.userId')
		);
	}


	public function testAddDuplicateJoinAppendsAnotherJoin(): void
	{
		$this->assertBuilder(
			[
				'SELECT * FROM one AS [o] '
				. 'LEFT JOIN two AS [t] ON (o.userId = t.userId) '
				. 'LEFT JOIN two AS [t] ON (o.userId = t.userId)',
			],
			$this->builder()
				->from('one', 'o')
				->select('*')
				->addLeftJoin('two AS [t]', 'o.userId = t.userId')
				->addLeftJoin('two AS [t]', 'o.userId = t.userId')
		);
	}


	public function testJoinOnceDeduplicatesSameJoin(): void
	{
		$this->assertBuilder(
			[
				'SELECT * FROM one AS [o] '
				. 'LEFT JOIN two AS [t] ON (o.userId = t.userId)',
			],
			$this->builder()
				->from('one', 'o')
				->select('*')
				->joinOnce('LEFT', 'two AS [t]', 'o.userId = t.userId', [])
				->joinOnce('LEFT', 'two AS [t]', 'o.userId = t.userId', [])
		);
	}


	public function testJoinOnceHashSuffixDistinguishesJoins(): void
	{
		$this->assertBuilder(
			[
				'SELECT * FROM one AS [o] '
				. 'LEFT JOIN %table ON (%table.id = tbl.id) '
				. 'LEFT JOIN %table ON (%table.id = tbl.id)',
				't1',
				't1',
				't2',
				't2',
			],
			$this->builder()
				->from('one', 'o')
				->select('*')
				->joinOnce('LEFT', '%table', '%table.id = tbl.id', ['t1', 't1'], 'join-1')
				->joinOnce('LEFT', '%table', '%table.id = tbl.id', ['t2', 't2'], 'join-2')
		);
	}
}


$test = new QueryBuilderJoinsTest();
$test->run();
