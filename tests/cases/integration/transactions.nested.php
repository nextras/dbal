<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class TransactionsNestedTest extends IntegrationTestCase
{
	public function testRollback()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DELETE FROM tags WHERE name = %s', '_NTRANS_ROLLBACK_');

		$this->connection->beginTransaction();
		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_NTRANS_ROLLBACK_'
		]);
		Assert::same(1, $this->connection->getAffectedRows());
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_ROLLBACK_')->fetchField());


		$this->connection->beginTransaction();
		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_NTRANS_ROLLBACK_'
		]);
		Assert::same(2, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_ROLLBACK_')->fetchField());


		$this->connection->rollbackTransaction();
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_ROLLBACK_')->fetchField());


		$this->connection->rollbackTransaction();
		Assert::same(0, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_ROLLBACK_')->fetchField());
	}


	public function testCommitOuter()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DELETE FROM tags WHERE name = %s', '_NTRANS_COMMIT_');

		$this->connection->beginTransaction();
		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_NTRANS_COMMIT_'
		]);
		Assert::same(1, $this->connection->getAffectedRows());
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_COMMIT_')->fetchField());


		$this->connection->beginTransaction();
		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_NTRANS_COMMIT_'
		]);
		Assert::same(2, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_COMMIT_')->fetchField());


		$this->connection->rollbackTransaction();
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_COMMIT_')->fetchField());


		$this->connection->commitTransaction();
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_COMMIT_')->fetchField());
	}


	public function testCommitInner()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DELETE FROM tags WHERE name = %s', '_NTRANS_COMMIT2_');

		$this->connection->beginTransaction();
		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_NTRANS_COMMIT2_'
		]);
		Assert::same(1, $this->connection->getAffectedRows());
		Assert::same(1, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_COMMIT2_')->fetchField());


		$this->connection->beginTransaction();
		$this->connection->query('INSERT INTO tags %values', [
			'name' => '_NTRANS_COMMIT2_'
		]);
		Assert::same(2, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_COMMIT2_')->fetchField());


		$this->connection->commitTransaction();
		Assert::same(2, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_COMMIT2_')->fetchField());


		$this->connection->rollbackTransaction();
		Assert::same(0, $this->connection->query('SELECT COUNT(*) FROM tags WHERE name = %s', '_NTRANS_COMMIT2_')->fetchField());
	}
}


$test = new TransactionsNestedTest();
$test->run();
