<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IResultAdapter;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ResultIntegrationTest extends IntegrationTestCase
{

	public function testEmptyResult()
	{
		$result = $this->connection->query('SELECT * FROM books WHERE 1=2');
		Assert::equal([], iterator_to_array($result));
	}


	public function testSetupNormalization()
	{
		$this->initData($this->connection);

		$result = $this->connection->query('SELECT * FROM tag_followers WHERE tag_id = 1 AND author_id = 1');

		$result->setValueNormalization(FALSE); // test reenabling
		$result->setValueNormalization(
			IResultAdapter::ALL_TYPES & ~IResultAdapter::TYPE_DATETIME
		);

		$follower = $result->fetch();

		Assert::same(1, $follower->tag_id);
		Assert::same(1, $follower->author_id);
		Assert::type('string', $follower->created_at);
		Assert::same('2014-01-01 00:10:00', (new \DateTime($follower->created_at))->format('Y-m-d H:i:s'));
	}

}


$test = new ResultIntegrationTest();
$test->run();
