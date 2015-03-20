<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class QueryBuilderJoinsTest extends QueryBuilderTestCase
{

	public function testBasics()
	{
		$this->assertBuilder(
			[
				'SELECT * FROM one [o] ' .
				'LEFT JOIN two [t] ON (o.userId = t.userId) ' .
				'INNER JOIN three [th] ON (t.userId = th.userId) ' .
				'RIGHT JOIN four [f] ON (th.userId = f.userId)'
			],
			$this->builder()
				->from('one', 'o')
				->select('*')
				->leftJoin('o', 'two', 't', 'o.userId = t.userId')
				->innerJoin('t', 'three', 'th', 't.userId = th.userId')
				->rightJoin('th', 'four', 'f', 'th.userId = f.userId')
		);
	}


	public function testValidateMissingAlias()
	{
		Assert::throws(function() {

			$this->builder()
				->from('one', 'o')
				->innerJoin('t', 'three', 'th', 't.userId = th.userId')
				->rightJoin('th', 'four', 'f', 'th.userId = f.userId')
				->getQuerySql();

		}, 'Nextras\Dbal\Exceptions\InvalidStateException', "Unknown alias 't'.");
	}


	public function testOverride()
	{
		$builder = $this->builder()
				->from('one', 'o')
				->leftJoin('o', 'two', 't', 'o.userId = t.userId')
				->innerJoin('t', 'three', 't', 't.userId = th.userId');

		$this->assertBuilder(
			[
				'SELECT * FROM one [o] ' .
				'INNER JOIN three [t] ON (t.userId = th.userId)'
			],
			$builder
		);

		Assert::same([
			'type' => 'INNER',
			'from' => 't',
			'table' => 'three',
			'alias' => 't',
			'on' => 't.userId = th.userId',
		], $builder->getJoin('t'));
	}

}


$test = new QueryBuilderJoinsTest();
$test->run();
