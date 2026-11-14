<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Exception\InvalidStateException;
use PDO;
use PDOException;
use Tester\Assert;
use Tester\Environment;
use TypeError;

require_once __DIR__ . '/../../bootstrap.php';

if (!extension_loaded('pdo_sqlite')) {
	Environment::skip('PDO SQLite is required.');
}


class TransactionFailureTest extends TestCase
{
	public function testErrorRollsBackWrites(): void
	{
		$connection = $this->createConnection();
		Assert::exception(function () use ($connection): void {
			$connection->transactional(static function (Connection $connection): void {
				$connection->query('INSERT INTO transaction_test VALUES (1)');
				throw new TypeError('Application failed');
			});
		}, TypeError::class);
		Assert::same(0, $connection->getTransactionNestedIndex());
		Assert::same(0, $connection->query('SELECT COUNT(*) FROM transaction_test')->fetchField());
	}


	public function testUnclosedSavepointDoesNotLeakTransaction(): void
	{
		$connection = $this->createConnection();
		Assert::exception(function () use ($connection): void {
			$connection->transactional(static function (Connection $connection): void {
				$connection->query('INSERT INTO transaction_test VALUES (1)');
				$connection->beginTransaction();
				$connection->query('INSERT INTO transaction_test VALUES (2)');
			});
		}, InvalidStateException::class);
		Assert::same(0, $connection->getTransactionNestedIndex());
		Assert::same(0, $connection->query('SELECT COUNT(*) FROM transaction_test')->fetchField());
	}


	public function testNestedFailurePreservesOuterWrites(): void
	{
		$connection = $this->createConnection();
		$connection->beginTransaction();
		$connection->query('INSERT INTO transaction_test VALUES (1)');
		Assert::exception(function () use ($connection): void {
			$connection->transactional(static function (Connection $connection): void {
				$connection->query('INSERT INTO transaction_test VALUES (2)');
				$connection->beginTransaction();
				$connection->query('INSERT INTO transaction_test VALUES (3)');
				throw new TypeError('Application failed');
			});
		}, TypeError::class);
		Assert::same(1, $connection->getTransactionNestedIndex());
		Assert::same([1], $connection->query('SELECT id FROM transaction_test')->fetchPairs(null, 'id'));
		$connection->commitTransaction();
		Assert::same([1], $connection->query('SELECT id FROM transaction_test')->fetchPairs(null, 'id'));
	}


	/**
	 * @dataProvider provideErrorModes
	 */
	public function testDeferredCommitFailureRollsBack(int $errorMode): void
	{
		$connection = $this->createConnection();
		$pdo = $connection->getDriver()->getResourceHandle();
		assert($pdo instanceof PDO);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, $errorMode);
		$connection->query('CREATE TABLE parent (id INTEGER PRIMARY KEY)');
		$connection->query('CREATE TABLE child (parent_id INTEGER REFERENCES parent (id) DEFERRABLE INITIALLY DEFERRED)');

		$exception = Assert::exception(function () use ($connection): void {
			$connection->transactional(static function (Connection $connection): void {
				$connection->query('INSERT INTO child VALUES (42)');
			});
		}, ForeignKeyConstraintViolationException::class);
		Assert::same(0, $connection->getTransactionNestedIndex());
		Assert::same(0, $connection->query('SELECT COUNT(*) FROM child')->fetchField());
		if ($errorMode === PDO::ERRMODE_EXCEPTION) {
			Assert::type(PDOException::class, $exception->getPrevious());
		} else {
			Assert::null($exception->getPrevious());
		}

		$connection->transactional(static function (Connection $connection): void {
			$connection->query('INSERT INTO parent VALUES (42)');
			$connection->query('INSERT INTO child VALUES (42)');
		});
		Assert::same(1, $connection->query('SELECT COUNT(*) FROM child')->fetchField());
	}


	public function provideErrorModes(): array
	{
		return [[PDO::ERRMODE_SILENT], [PDO::ERRMODE_EXCEPTION]];
	}


	private function createConnection(): Connection
	{
		$connection = new Connection(['driver' => 'pdo_sqlite']);
		$connection->query('CREATE TABLE transaction_test (id INTEGER PRIMARY KEY)');
		return $connection;
	}
}


(new TransactionFailureTest())->run();
