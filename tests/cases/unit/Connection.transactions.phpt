<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;


use Mockery;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\TransactionRollbackException;
use Nextras\Dbal\Platforms\IPlatform;
use RuntimeException;
use Tester\Assert;
use TypeError;

require_once __DIR__ . '/../../bootstrap.php';


class ConnectionTransactionsTest extends TestCase
{
	public function testBeginFailureDoesNotChangeDepth(): void
	{
		$connection = $this->createConnection($driver);
		$failure = new DriverException('BEGIN failed');
		$driver->shouldReceive('beginTransaction')->once()->ordered()->andThrow($failure);
		$driver->shouldReceive('beginTransaction')->once()->ordered();

		Assert::same($failure, Assert::exception(fn() => $connection->beginTransaction(), DriverException::class));
		Assert::same(0, $connection->getTransactionNestedIndex());
		$connection->beginTransaction();
		Assert::same(1, $connection->getTransactionNestedIndex());
	}


	public function testSavepointFailureDoesNotChangeDepth(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once()->ordered()
			->andThrow(new DriverException('SAVEPOINT failed'));
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once()->ordered();

		$connection->beginTransaction();
		Assert::exception(fn() => $connection->beginTransaction(), DriverException::class);
		Assert::same(1, $connection->getTransactionNestedIndex());
		$connection->beginTransaction();
		Assert::same(2, $connection->getTransactionNestedIndex());
	}


	public function testThrowableIsRolledBack(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('rollbackTransaction')->once();
		$failure = new TypeError('Application failed');

		$thrown = Assert::exception(fn() => $connection->transactional(static fn() => throw $failure), TypeError::class);
		Assert::same($failure, $thrown);
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testCommitFailureIsRolledBack(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$failure = new DriverException('COMMIT failed');
		$driver->shouldReceive('commitTransaction')->once()->andThrow($failure);
		$driver->shouldReceive('rollbackTransaction')->once();

		Assert::same($failure, Assert::exception(fn() => $connection->transactional(static fn() => 42), DriverException::class));
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testUnclosedTransactionsAreRolledBackOnFailure(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once();
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_3')->once();
		$driver->shouldReceive('rollbackSavepoint')->with('NEXTRAS_SAVEPOINT_3')->once()->ordered();
		$driver->shouldReceive('rollbackSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once()->ordered();
		$driver->shouldReceive('rollbackTransaction')->once()->ordered();
		$failure = new RuntimeException('Application failed');

		$thrown = Assert::exception(function () use ($connection, $failure): void {
			$connection->transactional(static function (Connection $connection) use ($failure): void {
				$connection->beginTransaction();
				$connection->beginTransaction();
				throw $failure;
			});
		}, RuntimeException::class);
		Assert::same($failure, $thrown);
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testUnbalancedCallbackCannotCommit(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once();
		$driver->shouldReceive('rollbackSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once();
		$driver->shouldReceive('rollbackTransaction')->once();

		Assert::exception(function () use ($connection): void {
			$connection->transactional(static fn(Connection $connection) => $connection->beginTransaction());
		}, InvalidStateException::class, 'Transaction nesting level changed inside transactional() callback.');
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testNestedFailurePreservesCallerTransaction(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once();
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_3')->once();
		$driver->shouldReceive('rollbackSavepoint')->with('NEXTRAS_SAVEPOINT_3')->once()->ordered();
		$driver->shouldReceive('rollbackSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once()->ordered();

		$connection->beginTransaction();
		Assert::exception(function () use ($connection): void {
			$connection->transactional(static function (Connection $connection): void {
				$connection->beginTransaction();
				throw new TypeError('Application failed');
			});
		}, TypeError::class);
		Assert::same(1, $connection->getTransactionNestedIndex());
	}


	public function testNestedSuccessReturnsValue(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once();
		$driver->shouldReceive('releaseSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once();

		$connection->beginTransaction();
		Assert::same(42, $connection->transactional(static fn() => 42));
		Assert::same(1, $connection->getTransactionNestedIndex());
	}


	public function testCallbackRollbackDoesNotRollbackCaller(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('createSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once();
		$driver->shouldReceive('rollbackSavepoint')->with('NEXTRAS_SAVEPOINT_2')->once();

		$connection->beginTransaction();
		Assert::exception(function () use ($connection): void {
			$connection->transactional(static fn(Connection $connection) => $connection->rollbackTransaction());
		}, InvalidStateException::class);
		Assert::same(1, $connection->getTransactionNestedIndex());
	}


	public function testNestingWithoutSavepointsIsRejected(): void
	{
		$connection = $this->createConnection($driver, ['nestedTransactionsWithSavepoint' => false]);
		$driver->shouldReceive('beginTransaction')->once();
		$connection->beginTransaction();

		Assert::exception(fn() => $connection->transactional(static fn() => 42), InvalidStateException::class);
		Assert::same(1, $connection->getTransactionNestedIndex());
	}


	public function testRollbackFailurePreservesOriginalAndDisconnects(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$failure = new TypeError('Application failed');
		$rollbackFailure = new DriverException('ROLLBACK failed');
		$driver->shouldReceive('rollbackTransaction')->once()->andThrow($rollbackFailure);
		$driver->shouldReceive('disconnect')->once();

		$thrown = Assert::exception(fn() => $connection->transactional(static fn() => throw $failure), TransactionRollbackException::class);
		Assert::same($failure, $thrown->getPrevious());
		Assert::same($rollbackFailure, $thrown->getRollbackException());
		Assert::null($thrown->getDisconnectException());
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testDisconnectFailureIsPreserved(): void
	{
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$failure = new RuntimeException('Application failed');
		$rollbackFailure = new DriverException('ROLLBACK failed');
		$disconnectFailure = new RuntimeException('Disconnect failed');
		$driver->shouldReceive('rollbackTransaction')->once()->andThrow($rollbackFailure);
		$driver->shouldReceive('disconnect')->once()->andThrow($disconnectFailure);

		$thrown = Assert::exception(fn() => $connection->transactional(static fn() => throw $failure), TransactionRollbackException::class);
		Assert::same($failure, $thrown->getPrevious());
		Assert::same($rollbackFailure, $thrown->getRollbackException());
		Assert::same($disconnectFailure, $thrown->getDisconnectException());
	}


	private function createConnection(&$driver, array $config = []): Connection
	{
		$driver = Mockery::mock(IDriver::class);
		$driver->shouldReceive('isConnected')->once()->andReturn(false);
		$driver->shouldReceive('connect')->once();
		$driver->shouldReceive('createPlatform')->once()->andReturn(Mockery::mock(IPlatform::class));
		return new Connection(['driver' => $driver] + $config);
	}
}


(new ConnectionTransactionsTest())->run();
