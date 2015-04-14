<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Connection;
use Nextras\Dbal\InvalidStateException;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../bootstrap.php';


class TransactionsTest extends IntegrationTestCase
{

	public function testRollback()
	{
		$this->lockConnection($this->connection);
		$this->connection->beginTransaction();

		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_TRANS_ROLLBACK_'
		]);

		Assert::same(1, $this->connection->getAffectedRows());
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_ROLLBACK_')->fetchField());

		$this->connection->rollbackTransaction();

		Assert::same(0, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_ROLLBACK_')->fetchField());
	}


	public function testCommit()
	{
		$this->lockConnection($this->connection);
		$this->connection->beginTransaction();

		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_TRANS_COMMIT_'
		]);

		Assert::same(1, $this->connection->getAffectedRows());
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_COMMIT_')->fetchField());

		$this->connection->commitTransaction();

		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_COMMIT_')->fetchField());
	}


	public function testTransactionalFail()
	{
		$this->lockConnection($this->connection);
		Assert::exception(function() {
			$this->connection->transactional(function(Connection $connection) {
				$connection->query('INSERT INTO tags %values', [
					'name' => '_TRANS_TRANSACTIONAL_'
				]);

				Assert::same(1, $connection->getAffectedRows());
				Assert::same(1, $connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_')->fetchField());

				throw new InvalidStateException('ABORT TRANSACTION');
			});
		}, 'Nextras\Dbal\InvalidStateException', 'ABORT TRANSACTION');

		Assert::same(0, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_')->fetchField());
	}


	public function testTransactionalOk()
	{
		$this->lockConnection($this->connection);
		$this->connection->transactional(function(Connection $connection) {
			$connection->query('INSERT INTO tags %values', [
				'name' => '_TRANS_TRANSACTIONAL_OK_'
			]);

			Assert::same(1, $connection->getAffectedRows());
			Assert::same(1, $connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_OK_')->fetchField());
		});

		$this->connection->reconnect();
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_OK_')->fetchField());
	}

}


$test = new TransactionsTest();
$test->run();
