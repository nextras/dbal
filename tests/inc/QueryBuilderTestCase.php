<?php

namespace NextrasTests\Dbal;

use Mockery;
use Mockery\MockInterface;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Tester\Assert;


class QueryBuilderTestCase extends TestCase
{
	/** @var MockInterface */
	protected $driver;


	public function setUp()
	{
		parent::setUp();
		$this->driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');
	}


	protected function builder()
	{
		return new QueryBuilder($this->driver);
	}


	protected function assertBuilder($expected, QueryBuilder $builder)
	{
		$args = $builder->getQueryParameters();
		array_unshift($args, $builder->getQuerySql());
		Assert::same($expected, $args);
	}

}
