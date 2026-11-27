<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;


use Mockery;
use Mockery\MockInterface;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\IAsyncQueryDriver;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class AsyncRecordingLogger implements ILogger
{
	/** @var list<array{string, string, float, mixed}> */
	public array $queries = [];


	public function onConnect(): void
	{
	}


	public function onDisconnect(): void
	{
	}


	public function onQuery(string $sqlQuery, float $timeTaken, Result|null $result): void
	{
		$this->queries[] = ['query', $sqlQuery, $timeTaken, $result];
	}


	public function onQueryException(string $sqlQuery, float $timeTaken, DriverException|null $exception): void
	{
		$this->queries[] = ['exception', $sqlQuery, $timeTaken, $exception];
	}
}


class ConnectionAsyncTest extends TestCase
{
	public function testUnsupportedDriverRunsSynchronously(): void
	{
		$driver = Mockery::mock(IDriver::class);
		$connection = $this->createConnection($driver, $logger);
		$result = $this->createResult();
		$driver->shouldReceive('query')->with('SQL(SELECT %i, 1)')->once()->andReturn($result);
		$driver->shouldReceive('getQueryElapsedTime')->andReturn(0.5);

		Assert::false($connection->supportsAsyncQueries());
		$pendingResult = $connection->queryAsync('SELECT %i', 1);
		Assert::true($pendingResult->isResolved());
		Assert::same($result, $pendingResult->getResult());
		Assert::same([['query', 'SQL(SELECT %i, 1)', 0.5, $result]], $logger->queries);
	}


	public function testUnsupportedBuildRunsSynchronously(): void
	{
		$driver = Mockery::mock(IAsyncQueryDriver::class);
		$driver->shouldReceive('isAsyncQuerySupported')->andReturn(false);
		$driver->shouldNotReceive('sendQuery');
		$connection = $this->createConnection($driver, $logger);
		$result = $this->createResult();
		$driver->shouldReceive('query')->with('SQL(SELECT 1)')->once()->andReturn($result);
		$driver->shouldReceive('getQueryElapsedTime')->andReturn(0.5);

		Assert::false($connection->supportsAsyncQueries());
		$pendingResult = $connection->queryArgsAsync(['SELECT 1']);
		Assert::true($pendingResult->isResolved());
		Assert::same($result, $pendingResult->getResult());
		Assert::count(1, $logger->queries);
	}


	public function testUnsupportedDriverStoresFailureInPendingResult(): void
	{
		$driver = Mockery::mock(IDriver::class);
		$connection = $this->createConnection($driver, $logger);
		$failure = new DriverException('Query failed');
		$driver->shouldReceive('query')->once()->andThrow($failure);
		$driver->shouldReceive('getQueryElapsedTime')->andReturn(0.5);

		$pendingResult = $connection->queryAsync('SELECT 1'); // the same error path as with an async driver
		Assert::true($pendingResult->isResolved());
		Assert::same($failure, Assert::exception(fn() => $pendingResult->getResult(), DriverException::class));
		Assert::same([['exception', 'SQL(SELECT 1)', 0.5, $failure]], $logger->queries);
	}


	public function testSupportedSendsAndReapsOnce(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$result = $this->createResult();
		$driver->shouldReceive('sendQuery')->with('SQL(SELECT %i, 1)')->once();
		$driver->shouldReceive('reapQuery')->once()->andReturn($result);
		$driver->shouldReceive('getQueryElapsedTime')->andReturn(0.25);

		Assert::true($connection->supportsAsyncQueries());
		$pendingResult = $connection->queryAsync('SELECT %i', 1);
		Assert::false($pendingResult->isResolved());
		Assert::same([], $logger->queries);

		Assert::same($result, $pendingResult->getResult());
		Assert::true($pendingResult->isResolved());
		Assert::same($result, $pendingResult->getResult());
		Assert::same([['query', 'SQL(SELECT %i, 1)', 0.25, $result]], $logger->queries);
	}


	public function testQueryArgsAsyncProcessesLikeQueryArgs(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$driver->shouldReceive('sendQuery')->with('SQL(SELECT %i, 1)')->once()->ordered();
		$driver->shouldReceive('reapQuery')->once()->ordered()->andReturn($this->createResult());
		$driver->shouldReceive('sendQuery')->with('SQL(SELECT %i, 2)')->once()->ordered();
		$driver->shouldReceive('reapQuery')->once()->ordered()->andReturn($this->createResult());

		$connection->queryArgsAsync('SELECT %i', [1])->getResult();
		$connection->queryArgsAsync(['SELECT %i', 2])->getResult();
		Assert::count(2, $logger->queries);
	}


	public function testReapFailureIsThrownByGetResult(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$failure = new DriverException('Query failed');
		$driver->shouldReceive('sendQuery')->once();
		$driver->shouldReceive('reapQuery')->once()->andThrow($failure);
		$driver->shouldReceive('getQueryElapsedTime')->andReturn(0.75);

		$pendingResult = $connection->queryAsync('SELECT 1');
		Assert::same($failure, Assert::exception(fn() => $pendingResult->getResult(), DriverException::class));
		Assert::same($failure, Assert::exception(fn() => $pendingResult->getResult(), DriverException::class));
		Assert::true($pendingResult->isResolved());
		Assert::same([['exception', 'SQL(SELECT 1)', 0.75, $failure]], $logger->queries);
	}


	public function testSendFailureIsThrownImmediately(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$failure = new DriverException('Send failed');
		$driver->shouldReceive('sendQuery')->once()->andThrow($failure);
		$driver->shouldNotReceive('reapQuery');
		$driver->shouldReceive('getQueryElapsedTime')->andReturn(0.0);
		$driver->shouldReceive('query')->once()->andReturn($this->createResult());

		Assert::same($failure, Assert::exception(fn() => $connection->queryAsync('SELECT 1'), DriverException::class));
		Assert::same([['exception', 'SQL(SELECT 1)', 0.0, $failure]], $logger->queries);
		$connection->query('SELECT 2');
	}


	public function testConnectsOnDemand(): void
	{
		$driver = Mockery::mock(IAsyncQueryDriver::class);
		$driver->shouldReceive('isAsyncQuerySupported')->andReturn(true);
		$driver->shouldReceive('getQueryElapsedTime')->andReturn(0.0)->byDefault();
		$driver->shouldReceive('isConnected')->once()->andReturn(false);
		$driver->shouldReceive('connect')->once()->ordered();
		$driver->shouldReceive('sendQuery')->once()->ordered();
		$connection = new Connection(['driver' => $driver, 'sqlProcessorFactory' => $this->createSqlProcessorFactory()]);

		Assert::false($connection->queryAsync('SELECT 1')->isResolved());
	}


	public function testResolvedResultIsNotReapedAgain(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$result = $this->createResult();
		$driver->shouldReceive('sendQuery')->once();
		$driver->shouldReceive('reapQuery')->once()->andReturn($result);
		$driver->shouldReceive('getAffectedRows')->twice()->andReturn(3);

		$pendingResult = $connection->queryAsync('SELECT 1');
		Assert::same(3, $connection->getAffectedRows());
		Assert::true($pendingResult->isResolved());
		Assert::same($result, $pendingResult->getResult());
		Assert::same(3, $connection->getAffectedRows());
		Assert::count(1, $logger->queries);
	}


	public function testSequentialAsyncQueries(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$first = $this->createResult();
		$second = $this->createResult();
		$driver->shouldReceive('sendQuery')->with('SQL(SELECT 1)')->once()->ordered();
		$driver->shouldReceive('reapQuery')->once()->ordered()->andReturn($first);
		$driver->shouldReceive('sendQuery')->with('SQL(SELECT 2)')->once()->ordered();
		$driver->shouldReceive('reapQuery')->once()->ordered()->andReturn($second);

		$firstPending = $connection->queryAsync('SELECT 1');
		$secondPending = $connection->queryAsync('SELECT 2');
		Assert::true($firstPending->isResolved());
		Assert::false($secondPending->isResolved());
		Assert::same($second, $secondPending->getResult());
		Assert::same($first, $firstPending->getResult());
		Assert::same(['SQL(SELECT 1)', 'SQL(SELECT 2)'], array_column($logger->queries, 1));
	}


	/**
	 * @return list<array{string, callable(Connection, IAsyncQueryDriver&MockInterface): void}>
	 */
	public function getOperations(): array
	{
		return [
			['query', function (Connection $connection, $driver): void {
				$driver->shouldReceive('query')->with('SQL(SELECT 2)')->once()->ordered()->andReturn($this->createResult());
				$connection->query('SELECT 2');
			}],
			['queryArgs', function (Connection $connection, $driver): void {
				$driver->shouldReceive('query')->with('SQL(SELECT 2)')->once()->ordered()->andReturn($this->createResult());
				$connection->queryArgs('SELECT 2');
			}],
			['queryAsync', function (Connection $connection, $driver): void {
				$driver->shouldReceive('sendQuery')->with('SQL(SELECT 2)')->once()->ordered();
				$connection->queryAsync('SELECT 2');
			}],
			['getLastInsertedId', function (Connection $connection, $driver): void {
				$driver->shouldReceive('getLastInsertedId')->once()->ordered()->andReturn(1);
				Assert::same(1, $connection->getLastInsertedId());
			}],
			['getAffectedRows', function (Connection $connection, $driver): void {
				$driver->shouldReceive('getAffectedRows')->once()->ordered()->andReturn(2);
				Assert::same(2, $connection->getAffectedRows());
			}],
			['beginTransaction', function (Connection $connection, $driver): void {
				$driver->shouldReceive('beginTransaction')->once()->ordered();
				$connection->beginTransaction();
			}],
			['commitTransaction', function (Connection $connection, $driver): void {
				$driver->shouldReceive('commitTransaction')->once()->ordered();
				$connection->commitTransaction();
			}],
			['rollbackTransaction', function (Connection $connection, $driver): void {
				$driver->shouldReceive('rollbackTransaction')->once()->ordered();
				$connection->rollbackTransaction();
			}],
			['transactional', function (Connection $connection, $driver): void {
				$driver->shouldReceive('beginTransaction')->once()->ordered();
				$driver->shouldReceive('commitTransaction')->once()->ordered();
				Assert::same(42, $connection->transactional(static fn() => 42));
			}],
			['createSavepoint', function (Connection $connection, $driver): void {
				$driver->shouldReceive('createSavepoint')->with('sp')->once()->ordered();
				$connection->createSavepoint('sp');
			}],
			['releaseSavepoint', function (Connection $connection, $driver): void {
				$driver->shouldReceive('releaseSavepoint')->with('sp')->once()->ordered();
				$connection->releaseSavepoint('sp');
			}],
			['rollbackSavepoint', function (Connection $connection, $driver): void {
				$driver->shouldReceive('rollbackSavepoint')->with('sp')->once()->ordered();
				$connection->rollbackSavepoint('sp');
			}],
			['setTransactionIsolationLevel', function (Connection $connection, $driver): void {
				$driver->shouldReceive('setTransactionIsolationLevel')->with(IConnection::TRANSACTION_SERIALIZABLE)->once()->ordered();
				$connection->setTransactionIsolationLevel(IConnection::TRANSACTION_SERIALIZABLE);
			}],
			['ping', function (Connection $connection, $driver): void {
				$driver->shouldReceive('ping')->once()->ordered()->andReturn(true);
				Assert::true($connection->ping());
			}],
			['disconnect', function (Connection $connection, $driver): void {
				$driver->shouldReceive('disconnect')->once()->ordered();
				$connection->disconnect();
			}],
			['reconnect', function (Connection $connection, $driver): void {
				$driver->shouldReceive('disconnect')->once()->ordered();
				$driver->shouldReceive('connect')->once()->ordered();
				$connection->reconnect();
			}],
			['reconnectWithConfig', function (Connection $connection, $driver): void {
				$driver->shouldReceive('disconnect')->once()->ordered();
				$newDriver = Mockery::mock(IDriver::class);
				$newDriver->shouldReceive('connect')->once();
				$connection->reconnectWithConfig(['driver' => $newDriver]);
				Assert::same($newDriver, $connection->getDriver());
			}],
		];
	}


	/**
	 * Operations that do not commit anything collect a failed pending query without throwing its failure.
	 * @return list<array{string, callable(Connection, IAsyncQueryDriver&MockInterface): void}>
	 */
	public function getNonCommittingOperations(): array
	{
		return array_values(array_filter(
			$this->getOperations(),
			fn(array $operation) => !in_array($operation[0], ['commitTransaction', 'releaseSavepoint', 'transactional'], true),
		));
	}


	/**
	 * @dataProvider getNonCommittingOperations
	 */
	public function testOperationResolvesPendingQuerySilently(string $name, callable $operation): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$failure = new DriverException('Query failed');
		$driver->shouldReceive('sendQuery')->with('SQL(SELECT 1)')->once()->ordered();
		$driver->shouldReceive('reapQuery')->once()->ordered()->andThrow($failure);

		$pendingResult = $connection->queryAsync('SELECT 1');
		$operation($connection, $driver);

		Assert::true($pendingResult->isResolved(), $name);
		Assert::same($failure, Assert::exception(fn() => $pendingResult->getResult(), DriverException::class));
		Assert::same(['exception', 'SQL(SELECT 1)', 0.0, $failure], $logger->queries[0]);
	}


	/**
	 * @dataProvider getOperations
	 */
	public function testOperationResolvesPendingQuery(string $name, callable $operation): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$result = $this->createResult();
		$driver->shouldReceive('sendQuery')->with('SQL(SELECT 1)')->once()->ordered();
		$driver->shouldReceive('reapQuery')->once()->ordered()->andReturn($result);

		$pendingResult = $connection->queryAsync('SELECT 1');
		$operation($connection, $driver);

		Assert::true($pendingResult->isResolved(), $name);
		Assert::same($result, $pendingResult->getResult());
		Assert::same(['query', 'SQL(SELECT 1)', 0.0, $result], $logger->queries[0]);
	}


	public function testCommitThrowsUnreadPendingFailure(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver);
		$failure = new DriverException('Query failed');
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('sendQuery')->once();
		$driver->shouldReceive('reapQuery')->once()->andThrow($failure);
		$driver->shouldNotReceive('commitTransaction');

		$connection->beginTransaction();
		$connection->queryAsync('INSERT 1'); // never read
		Assert::same($failure, Assert::exception(fn() => $connection->commitTransaction(), DriverException::class));
		Assert::same(1, $connection->getTransactionNestedIndex()); // still open, the caller has to roll back
	}


	public function testCommitThrowsFailureCollectedSilentlyEarlier(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver);
		$failure = new DriverException('Query failed');
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('sendQuery')->once();
		$driver->shouldReceive('reapQuery')->once()->andThrow($failure);
		$driver->shouldReceive('query')->once()->andReturn($this->createResult());
		$driver->shouldNotReceive('commitTransaction');

		$connection->beginTransaction();
		$connection->queryAsync('INSERT 1'); // never read
		$connection->query('SELECT 2'); // collects the failure silently
		Assert::same($failure, Assert::exception(fn() => $connection->commitTransaction(), DriverException::class));
	}


	public function testCommitIgnoresFailureTheCallerHasRead(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->once();
		$driver->shouldReceive('sendQuery')->once();
		$driver->shouldReceive('reapQuery')->once()->andThrow(new DriverException('Query failed'));
		$driver->shouldReceive('commitTransaction')->once();

		$connection->beginTransaction();
		$pendingResult = $connection->queryAsync('INSERT 1');
		Assert::exception(fn() => $pendingResult->getResult(), DriverException::class); // handled by the caller
		$connection->commitTransaction();
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testReleaseSavepointThrowsUnreadPendingFailure(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver);
		$failure = new DriverException('Query failed');
		$driver->shouldReceive('sendQuery')->once();
		$driver->shouldReceive('reapQuery')->once()->andThrow($failure);
		$driver->shouldNotReceive('releaseSavepoint');

		$connection->queryAsync('INSERT 1');
		Assert::same($failure, Assert::exception(fn() => $connection->releaseSavepoint('sp'), DriverException::class));
	}


	public function testTransactionalRollsBackOnUnreadPendingFailure(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver);
		$failure = new DriverException('Query failed');
		$driver->shouldReceive('beginTransaction')->once()->ordered();
		$driver->shouldReceive('sendQuery')->once()->ordered();
		$driver->shouldReceive('reapQuery')->once()->ordered()->andThrow($failure);
		$driver->shouldReceive('rollbackTransaction')->once()->ordered();
		$driver->shouldNotReceive('commitTransaction');

		Assert::same($failure, Assert::exception(
			fn() => $connection->transactional(fn(Connection $connection) => $connection->queryAsync('INSERT 1')),
			DriverException::class,
		));
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testRollbackForgetsUnreadFailures(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver);
		$driver->shouldReceive('beginTransaction')->twice();
		$driver->shouldReceive('sendQuery')->once();
		$driver->shouldReceive('reapQuery')->once()->andThrow(new DriverException('Query failed'));
		$driver->shouldReceive('rollbackTransaction')->once();
		$driver->shouldReceive('commitTransaction')->once();

		$connection->beginTransaction();
		$connection->queryAsync('INSERT 1');
		$connection->rollbackTransaction();
		$connection->beginTransaction();
		$connection->commitTransaction(); // the rolled back failure does not leak into the next transaction
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testQueryByQueryBuilderAsync(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver);
		$result = $this->createResult();
		$builder = Mockery::mock(QueryBuilder::class);
		$builder->shouldReceive('getQuerySql')->andReturn('SELECT %i');
		$builder->shouldReceive('getQueryParameters')->andReturn([1]);
		$driver->shouldReceive('sendQuery')->with('SQL(SELECT %i, 1)')->once();
		$driver->shouldReceive('reapQuery')->once()->andReturn($result);

		Assert::same($result, $connection->queryByQueryBuilderAsync($builder)->getResult());
	}


	public function testPingWhenDisconnectedDoesNotTouchDriver(): void
	{
		$driver = $this->createAsyncDriver();
		$connection = $this->createConnection($driver, $logger);
		$driver->shouldReceive('sendQuery')->once();
		$driver->shouldReceive('reapQuery')->once()->andReturn($this->createResult());
		$driver->shouldReceive('disconnect')->once();
		$driver->shouldNotReceive('ping');

		$pendingResult = $connection->queryAsync('SELECT 1');
		$connection->disconnect();
		Assert::true($pendingResult->isResolved());
		Assert::false($connection->ping());
	}


	private function createAsyncDriver(): IAsyncQueryDriver&MockInterface
	{
		$driver = Mockery::mock(IAsyncQueryDriver::class);
		$driver->shouldReceive('isAsyncQuerySupported')->andReturn(true);
		$driver->shouldReceive('getQueryElapsedTime')->andReturn(0.0)->byDefault();
		return $driver;
	}


	private function createConnection(IDriver&MockInterface $driver, AsyncRecordingLogger|null &$logger = null): Connection
	{
		$driver->shouldReceive('isConnected')->once()->andReturn(false);
		$driver->shouldReceive('connect')->once()->ordered();
		$connection = new Connection(['driver' => $driver, 'sqlProcessorFactory' => $this->createSqlProcessorFactory()]);
		$connection->connect();
		$logger = new AsyncRecordingLogger();
		$connection->addLogger($logger);
		return $connection;
	}


	private function createSqlProcessorFactory(): ISqlProcessorFactory
	{
		$sqlProcessor = Mockery::mock(SqlProcessor::class);
		$sqlProcessor->shouldReceive('process')->andReturnUsing(
			static fn(array $args): string => 'SQL(' . implode(', ', $args) . ')',
		);
		$factory = Mockery::mock(ISqlProcessorFactory::class);
		$factory->shouldReceive('create')->andReturn($sqlProcessor);
		return $factory;
	}


	private function createResult(): Result
	{
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getNormalizers')->andReturn([]);
		return new Result($adapter);
	}
}


(new ConnectionAsyncTest())->run();
