<?php

/** @testCase */

namespace NextrasTests\Dbal;

require_once __DIR__ . '/../../bootstrap.php';


class QueryBuilderBasicsTest extends QueryBuilderTestCase
{

	public function testParametersOrder()
	{
		$this->assertBuilder(
			['SELECT * FROM test'],
			$this->builder()->from('test')
		);

		$this->assertBuilder(
			['SELECT CONCAT(%s) FROM test WHERE id = %i', 'foo', 1],
			$this->builder()->select('CONCAT(%s)', 'foo')->from('test')->where('id = %i', 1)
		);

		$this->assertBuilder(
			['SELECT CONCAT(%s) FROM test WHERE id = %i', 'foo', 1],
			$this->builder()->where('id = %i', 1)->from('test')->select('CONCAT(%s)', 'foo')
		);

		$this->assertBuilder(
			[
				'SELECT %i, %i ' .
				'FROM func(%i) [table] ' .
				'WHERE ((id = %s) OR (id2 = %i)) AND (id3 = %i) ' .
				'GROUP BY %column, %column ' .
				'HAVING (id = %s) AND (id2 = %i) ' .
				'ORDER BY FIELD(id, %i, %i), FIELD(id2, %i, %i)',

				4, 9,               // select
				3,                  // from
				'foo_w', 7, 8,      // where
				'foo_g', 'foo_g_2', // group by
				'foo_h', 6,         // having
				1, 2, 4, 5          // order by
			],
			$this->builder()
				->orderBy('FIELD(id, %i, %i)', 1, 2)
				->having('id = %s', 'foo_h')
				->groupBy('%column', 'foo_g')
				->where('id = %s', 'foo_w')
				->from('func(%i)', 'table', 3)
				->select('%i', 4)
				->addOrderBy('FIELD(id2, %i, %i)', 4, 5)
				->andHaving('id2 = %i', 6)
				->addGroupBy('%column', 'foo_g_2')
				->orWhere('id2 = %i', 7)
				->andWhere('id3 = %i', 8)
				->addSelect('%i', 9)
		);
	}


	public function testSelect()
	{
		$this->assertBuilder(
			['SELECT id FROM foo'],
			$this->builder()->from('foo')->addSelect('id')
		);

		$this->assertBuilder(
			['SELECT * FROM foo'],
			$this->builder()->from('foo')
		);
	}


	public function testAndMethods()
	{
		$this->assertBuilder(
			['SELECT * FROM foo WHERE id = %i', 1],
			$this->builder()
				->from('foo')
				->andWhere('id = %i', 1)
		);

		$this->assertBuilder(
			['SELECT * FROM foo WHERE id = %i', 1],
			$this->builder()
				->from('foo')
				->orWhere('id = %i', 1)
		);

		$this->assertBuilder(
			['SELECT * FROM foo HAVING id = %i', 1],
			$this->builder()
				->from('foo')
				->andHaving('id = %i', 1)
		);

		$this->assertBuilder(
			['SELECT * FROM foo HAVING id = %i', 1],
			$this->builder()
				->from('foo')
				->orHaving('id = %i', 1)
		);
	}

}


$test = new QueryBuilderBasicsTest();
$test->run();
