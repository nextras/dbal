<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlPreprocessorIntegrationTest extends IntegrationTestCase
{
	public function testEmptyInsert()
	{
		$this->connection->query('INSERT INTO table_with_defaults %values', []);
		$this->connection->query('INSERT INTO table_with_defaults %values[]', [[]]);
		$this->connection->query('INSERT INTO table_with_defaults %values[]', [[], []]);
		$count = $this->connection->query('SELECT COUNT(*) FROM table_with_defaults')->fetchField();
		Assert::equal(4, $count);
	}
}


$test = new SqlPreprocessorIntegrationTest();
$test->run();
