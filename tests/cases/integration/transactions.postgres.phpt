<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\Exception\RetryableException;
use Nextras\Dbal\Drivers\Exception\SerializationFailureException;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../bootstrap.php';


class TransactionsPostgresTest extends IntegrationTestCase
{
	public function testSerializationFailureAtCommit(): void
	{
		$this->checkSerializationFailureAtCommit();
	}


	public function testPdoSerializationFailureAtCommit(): void
	{
		if (!extension_loaded('pdo_pgsql')) {
			Environment::skip('PDO PostgreSQL is required.');
		}
		$this->checkSerializationFailureAtCommit('pdo_pgsql');
	}


	private function checkSerializationFailureAtCommit(string|null $driver = null): void
	{
		$first = $this->connection;
		$second = $this->createConnection($driver !== null ? ['driver' => $driver] : []);
		$this->lockConnection($first);
		$first->query('CREATE TABLE dbal_transaction_serialization (id INT PRIMARY KEY, value INT NOT NULL)');

		try {
			$first->query('INSERT INTO dbal_transaction_serialization VALUES (1, 0), (2, 0)');
			$first->beginTransaction();
			$first->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
			$second->beginTransaction();
			$second->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');

			Assert::same(0, $first->query('SELECT SUM(value) FROM dbal_transaction_serialization')->fetchField());
			Assert::same(0, $second->query('SELECT SUM(value) FROM dbal_transaction_serialization')->fetchField());
			$first->query('UPDATE dbal_transaction_serialization SET value = 1 WHERE id = 1');
			$second->query('UPDATE dbal_transaction_serialization SET value = 1 WHERE id = 2');
			$first->commitTransaction();

			$exception = Assert::exception(fn() => $second->commitTransaction(), SerializationFailureException::class);
			Assert::type(RetryableException::class, $exception);
			Assert::same('40001', $exception->getErrorSqlState());
		} finally {
			$second->disconnect();
			$first->reconnect();
			$first->query('DROP TABLE dbal_transaction_serialization');
		}
	}
}


(new TransactionsPostgresTest())->run();
