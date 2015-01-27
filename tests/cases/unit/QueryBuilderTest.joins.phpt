<?php

/** @testcase */

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
				->getQuerySQL();

		}, 'Nextras\Dbal\Exceptions\InvalidStateException', "Unknown alias 't'.");
	}

}


$test = new QueryBuilderJoinsTest();
$test->run();
