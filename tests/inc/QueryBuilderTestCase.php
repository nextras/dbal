<?php

namespace NextrasTests\Dbal;

use Mockery;
use Mockery\MockInterface;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Tester\Assert;


class QueryBuilderTestCase extends TestCase
{
	/** @var IPlatform|MockInterface */
	protected $platform;


	public function setUp()
	{
		parent::setUp();
		$this->platform = Mockery::mock(IPlatform::class);
	}


	protected function builder()
	{
		return new QueryBuilder($this->platform);
	}


	protected function assertBuilder($expected, QueryBuilder $builder)
	{
		$args = $builder->getQueryParameters();
		array_unshift($args, $builder->getQuerySql());
		Assert::same($expected, $args);
	}

}
