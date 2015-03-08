<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../bootstrap.php';


class TransactionsTest extends IntegrationTestCase
{

	public function testRollback()
	{
		Environment::lock('data', TEMP_DIR);
		$this->connection->beginTransaction();

		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_TRANS_ROLLBACK_'
		]);

		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_ROLLBACK_')->fetchField());

		$this->connection->rollbackTransaction();

		Assert::same(0, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_ROLLBACK_')->fetchField());
	}


	public function testCommit()
	{
		Environment::lock('data', TEMP_DIR);
		$this->connection->beginTransaction();

		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_TRANS_COMMIT_'
		]);

		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_COMMIT_')->fetchField());

		$this->connection->commitTransaction();

		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_COMMIT_')->fetchField());
	}

}


$test = new TransactionsTest();
$test->run();
