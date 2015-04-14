<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class QueryBuilderIntegrationTest extends IntegrationTestCase
{

	public function testLimitBy()
	{
		$this->initData($this->connection);

		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->from('books')->orderBy('id');
		$queryBuilder->select('id');
		$queryBuilder->limitBy(2);

		Assert::same([1, 2], $this->execute($queryBuilder));

		$queryBuilder->limitBy(NULL, NULL);
		Assert::same([1, 2, 3, 4], $this->execute($queryBuilder));

		$queryBuilder->limitBy(NULL, 2);
		Assert::same([3, 4], $this->execute($queryBuilder));

		$queryBuilder->limitBy(1, 2);
		Assert::same([3], $this->execute($queryBuilder));
	}


	private function execute(QueryBuilder $builder)
	{
		return $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters())
			->fetchPairs(NULL, 'id');
	}

}


$test = new QueryBuilderIntegrationTest();
$test->run();
