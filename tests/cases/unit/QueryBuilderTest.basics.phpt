<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Tester\Assert;


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
				'FROM func(%i) AS [table] ' .
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


	public function testResetingAddExpression()
	{
		$this->assertBuilder(
			['SELECT * FROM foo'],
			$this->builder()
				->from('foo')
				->select('id')
				->select(NULL)
		);

		$this->assertBuilder(
			['SELECT * FROM foo'],
			$this->builder()
				->from('foo')
				->groupBy('id')
				->groupBy(NULL)
		);

		$this->assertBuilder(
			['SELECT * FROM foo'],
			$this->builder()
				->from('foo')
				->orderBy('id')
				->orderBy(NULL)
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


	public function testLimitMethods()
	{
		$this->driver->shouldReceive('modifyLimitQuery')->with('SELECT * FROM foo', 10, NULL)->andReturn('X');

		$this->assertBuilder(
			['X'],
			$this->builder()
				->from('foo')
				->limitBy(10)
		);

		$this->driver->shouldReceive('modifyLimitQuery')->with('SELECT * FROM foo', 10, 20)->andReturn('Y');
		$this->assertBuilder(
			['Y'],
			$this->builder()
				->from('foo')
				->limitBy(10, 20)
		);

		Assert::false($this->builder()->hasLimitOffsetClause());
		Assert::true($this->builder()->limitBy(1)->hasLimitOffsetClause());
		Assert::true($this->builder()->limitBy(NULL, 2)->hasLimitOffsetClause());
		Assert::false($this->builder()->limitBy(1)->limitBy(NULL)->hasLimitOffsetClause());
	}


	public function testFrom()
	{
		$builder = $this->builder()->from('foo', NULL);
		Assert::same(NULL, $builder->getFromAlias());

		$builder = $this->builder()->from('foo', 'bar');
		Assert::same('bar', $builder->getFromAlias());

		Assert::exception(function() {
			$this->builder()->getFromAlias();
		}, 'Nextras\Dbal\InvalidStateException');
	}


	public function testExceptions()
	{
		Assert::exception(function() { $this->builder()->select(1); }, 'Nextras\Dbal\InvalidArgumentException');
		Assert::exception(function() { $this->builder()->addSelect(NULL); }, 'Nextras\Dbal\InvalidArgumentException');

		Assert::exception(function() { $this->builder()->where(1); }, 'Nextras\Dbal\InvalidArgumentException');
		Assert::exception(function() { $this->builder()->andWhere(NULL); }, 'Nextras\Dbal\InvalidArgumentException');
		Assert::exception(function() { $this->builder()->orWhere(NULL); }, 'Nextras\Dbal\InvalidArgumentException');

		Assert::exception(function() { $this->builder()->groupBy(1); }, 'Nextras\Dbal\InvalidArgumentException');
		Assert::exception(function() { $this->builder()->addGroupBy(NULL); }, 'Nextras\Dbal\InvalidArgumentException');

		Assert::exception(function() { $this->builder()->having(1); }, 'Nextras\Dbal\InvalidArgumentException');
		Assert::exception(function() { $this->builder()->andHaving(NULL); }, 'Nextras\Dbal\InvalidArgumentException');
		Assert::exception(function() { $this->builder()->orHaving(NULL); }, 'Nextras\Dbal\InvalidArgumentException');

		Assert::exception(function() { $this->builder()->orderBy(1); }, 'Nextras\Dbal\InvalidArgumentException');
		Assert::exception(function() { $this->builder()->addOrderBy(NULL); }, 'Nextras\Dbal\InvalidArgumentException');
	}


	public function testNulling()
	{
		$this->assertBuilder(
			['SELECT * FROM table'],
			$this->builder()
				->select('foo')
				->select(NULL)
				->from('table')
				->where('bar = 1')
				->where(NULL)
				->groupBy('foo')
				->groupBy(NULL)
				->having('baz')
				->having(NULL)
				->orderBy('bazz')
				->orderBy(NULL)
		);
	}

}


$test = new QueryBuilderBasicsTest();
$test->run();
