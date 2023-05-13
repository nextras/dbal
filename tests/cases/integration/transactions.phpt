<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Pdo\PdoDriver;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Platforms\SqlServerPlatform;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../bootstrap.php';


class TransactionsTest extends IntegrationTestCase
{
	public function testRollback()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DELETE FROM tags WHERE name = %s', '_TRANS_ROLLBACK_');

		$this->connection->beginTransaction();
		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_TRANS_ROLLBACK_',
		]);

		Assert::same(1, $this->connection->getAffectedRows());
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_ROLLBACK_')
			->fetchField());

		$this->connection->rollbackTransaction();

		Assert::same(0, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_ROLLBACK_')
			->fetchField());
	}


	public function testCommit()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DELETE FROM tags WHERE name = %s', '_TRANS_COMMIT_');

		$this->connection->beginTransaction();
		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_TRANS_COMMIT_',
		]);

		Assert::same(1, $this->connection->getAffectedRows());
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_COMMIT_')
			->fetchField());

		$this->connection->commitTransaction();

		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_COMMIT_')
			->fetchField());
	}


	public function testTransactionalFail()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DELETE FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_');

		Assert::exception(function() {
			$this->connection->transactional(function(Connection $connection) {
				$connection->query('INSERT INTO tags %values', [
					'name' => '_TRANS_TRANSACTIONAL_',
				]);

				Assert::same(1, $connection->getAffectedRows());
				Assert::same(1, $connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_')
					->fetchField());

				throw new InvalidStateException('ABORT TRANSACTION');
			});
		}, InvalidStateException::class, 'ABORT TRANSACTION');

		Assert::same(0, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_')
			->fetchField());
	}


	public function testTransactionalOk()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DELETE FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_OK_');

		$returnValue = $this->connection->transactional(function(Connection $connection) {
			$connection->query('INSERT INTO tags %values', [
				'name' => '_TRANS_TRANSACTIONAL_OK_',
			]);

			Assert::same(1, $connection->getAffectedRows());
			Assert::same(1, $connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_OK_')
				->fetchField());

			return 42;
		});

		$this->connection->reconnect();
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_TRANS_TRANSACTIONAL_OK_')
			->fetchField());
		Assert::same(42, $returnValue);
	}


	public function testTransactionWithoutBegin()
	{
		if (
			$this->connection->getPlatform() instanceof SqlServerPlatform
			|| $this->connection->getDriver() instanceof PdoDriver
		) {
			Environment::skip("Platform or driver does not support wrongly called transaction operations.");
		}

		$this->connection->connect();

		$this->connection->rollbackTransaction();
		$this->connection->rollbackTransaction();
		Assert::same(0, $this->connection->getTransactionNestedIndex());

		$this->connection->commitTransaction();
		$this->connection->commitTransaction();
		Assert::same(0, $this->connection->getTransactionNestedIndex());
	}


	public function testTransactionWithReconnect()
	{
		if (
			$this->connection->getPlatform() instanceof SqlServerPlatform
			|| $this->connection->getDriver() instanceof PdoDriver
		) {
			Environment::skip("Platform or driver does not support wrongly called transaction operations.");
		}

		$this->connection->connect();

		$this->connection->beginTransaction();
		$this->connection->reconnect();
		$this->connection->commitTransaction();

		$this->connection->beginTransaction();
		$this->connection->reconnect();
		$this->connection->rollbackTransaction();

		Environment::$checkAssertions = false;
	}


	public function testTransactionDeadlock()
	{
		$connection1 = $this->createConnection();
		$this->initData($connection1);
		$this->lockConnection($connection1);
		$connection1->beginTransaction();
		$connection1->query('SELECT * FROM books WHERE id = 1 FOR UPDATE');

		$connection2 = $this->createConnection();
		Assert::exception(function() use ($connection2) {
			$connection2->transactional(function() use ($connection2) {
				$connection2->transactional(function() use ($connection2) {
					$connection2->query('SELECT * FROM books WHERE id = 1 FOR UPDATE NOWAIT');
				});
			});
		}, DriverException::class);

//		Assert::same(-1, $connection2->getTransactionNestedIndex());
	}
}


$test = new TransactionsTest();
$test->run();
