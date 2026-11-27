<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\Pgsql\PgsqlDriver;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Result\Result;
use Tester\Assert;
use Tester\Environment;
use function microtime;

require_once __DIR__ . '/../../bootstrap.php';


class ConnectionAsyncTest extends IntegrationTestCase
{
	public function testUnsupportedDriverRunsSynchronously(): void
	{
		if ($this->connection->supportsAsyncQueries()) {
			Environment::skip('Driver supports async queries.');
		}

		$pending = $this->connection->queryAsync('SELECT %i AS i', 1);
		Assert::true($pending->isResolved());
		Assert::same(1, $pending->getResult()->fetchField());
		Assert::exception(fn() => $this->connection->queryAsync('SELECT * FROM non_existing_table'), QueryException::class);
	}


	public function testQueriesOnTwoConnectionsRunConcurrently(): void
	{
		$this->requireAsync();
		$other = $this->createConnection();
		$sleep = $this->connection->getDriver() instanceof PgsqlDriver ? 'pg_sleep(0.5)::text' : 'SLEEP(0.5)';

		$start = microtime(true);
		$a = $this->connection->queryAsync("SELECT $sleep AS s, %i AS i", 1);
		$b = $other->queryAsync("SELECT $sleep AS s, %i AS i", 2);
		Assert::false($a->isResolved());
		Assert::same(1, $a->getResult()->fetch()?->i);
		Assert::same(2, $b->getResult()->fetch()?->i);
		$elapsed = microtime(true) - $start;

		Assert::true($elapsed < 0.9, "Expected concurrent execution (sequential takes 1 s), took {$elapsed}s");
		$other->disconnect();
	}


	public function testResultIsNormalizedLikeQuery(): void
	{
		$this->requireAsync();
		$this->initData($this->connection);
		$datetime = $this->connection->getDriver() instanceof PgsqlDriver
			? "'2020-01-02 03:04:05'::timestamp"
			: "CAST('2020-01-02 03:04:05' AS DATETIME)";
		$sql = "SELECT books.*, $datetime AS dt, NULL AS n FROM books ORDER BY id";

		$expected = $this->connection->query($sql)->fetchAll();
		$actual = $this->connection->queryAsync($sql)->getResult()->fetchAll();
		Assert::equal($expected, $actual);
		Assert::type('int', $actual[0]->id);
		Assert::type(\DateTimeImmutable::class, $actual[0]->dt);
	}


	public function testFailureIsThrownByGetResultAndLoggedOnce(): void
	{
		$this->requireAsync();
		$this->initData($this->connection);
		$logger = $this->createRecordingLogger();
		$this->connection->addLogger($logger);

		$pending = $this->connection->queryAsync('INSERT INTO tags (id, name) VALUES (1, %s)', 'duplicate');
		$e1 = Assert::exception(fn() => $pending->getResult(), UniqueConstraintViolationException::class);
		$e2 = Assert::exception(fn() => $pending->getResult(), UniqueConstraintViolationException::class);
		Assert::same($e1, $e2);

		Assert::count(1, $logger->exceptions);
		Assert::same(1, $this->connection->query('SELECT %i AS i', 1)->fetchField()); // the connection is usable
	}


	public function testPendingQueryIsResolvedBeforeTheNextOperation(): void
	{
		$this->requireAsync();
		$this->connection->connect(); // the connection's own setup queries are not part of the test
		$logger = $this->createRecordingLogger();
		$this->connection->addLogger($logger);

		$first = $this->connection->queryAsync('SELECT %i AS i', 1);
		Assert::same(2, $this->connection->query('SELECT %i AS i', 2)->fetchField()); // resolves $first
		Assert::true($first->isResolved());
		Assert::same(1, $first->getResult()->fetchField());

		$failing = $this->connection->queryAsync('SELECT * FROM non_existing_table');
		$this->connection->beginTransaction(); // resolves $failing without throwing its failure
		$this->connection->rollbackTransaction();
		Assert::true($failing->isResolved());
		Assert::exception(fn() => $failing->getResult(), QueryException::class);

		Assert::same(['SELECT 1 AS i', 'SELECT 2 AS i'], array_slice($logger->queries, 0, 2));
		Assert::count(1, $logger->exceptions);
	}


	public function testDisconnectResolvesPendingQuery(): void
	{
		$this->requireAsync();
		$pending = $this->connection->queryAsync('SELECT %i AS i', 7);
		$this->connection->disconnect();
		Assert::same(7, $pending->getResult()->fetchField());
		Assert::same(8, $this->connection->query('SELECT %i AS i', 8)->fetchField());
	}


	private function requireAsync(): void
	{
		if (!$this->connection->supportsAsyncQueries()) {
			Environment::skip('Driver does not support async queries.');
		}
	}


	private function createRecordingLogger(): ILogger
	{
		return new class implements ILogger {
			/** @var list<string> */
			public array $queries = [];

			/** @var list<DriverException|null> */
			public array $exceptions = [];


			public function onConnect(): void
			{
			}


			public function onDisconnect(): void
			{
			}


			public function onQuery(string $sqlQuery, float $timeTaken, Result|null $result): void
			{
				$this->queries[] = $sqlQuery;
			}


			public function onQueryException(string $sqlQuery, float $timeTaken, DriverException|null $exception): void
			{
				$this->exceptions[] = $exception;
			}
		};
	}
}


$test = new ConnectionAsyncTest();
$test->run();
