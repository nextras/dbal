<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\Exception\DeadlockException;
use Nextras\Dbal\Drivers\Exception\LockWaitTimeoutException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\RetryableException;
use Nextras\Dbal\Drivers\Exception\SerializationFailureException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\Mysqli\MysqliDriver;
use Nextras\Dbal\Drivers\PdoMysql\PdoMysqlDriver;
use Nextras\Dbal\Drivers\PdoPgsql\PdoPgsqlDriver;
use Nextras\Dbal\Drivers\PdoSqlsrv\PdoSqlsrvDriver;
use Nextras\Dbal\Drivers\Pgsql\PgsqlDriver;
use Nextras\Dbal\Drivers\Sqlsrv\SqlsrvDriver;
use ReflectionMethod;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class RetryableExceptionsTest extends TestCase
{
	/**
	 * @dataProvider provideConcurrencyErrors
	 */
	public function testClassification(string $driverClass, int $code, string $state, string $exceptionClass): void
	{
		$driver = new $driverClass();
		$convert = new ReflectionMethod($driverClass, 'createException');
		foreach ([null, 'UPDATE example SET value = 1'] as $query) {
			$exception = $convert->invoke($driver, 'Concurrency failure', $code, $state, $query);
			Assert::same($exceptionClass, $exception::class);
			Assert::type(RetryableException::class, $exception);
			Assert::type(QueryException::class, $exception);
			Assert::same($code, $exception->getErrorCode());
			Assert::same($state, $exception->getErrorSqlState());
			Assert::same($query, $exception->getSqlQuery());
		}
	}


	public function provideConcurrencyErrors(): array
	{
		$cases = [];
		foreach ([MysqliDriver::class, PdoMysqlDriver::class] as $driver) {
			$cases[] = [$driver, 1213, '40001', DeadlockException::class];
			$cases[] = [$driver, 1205, 'HY000', LockWaitTimeoutException::class];
		}
		foreach ([PgsqlDriver::class, PdoPgsqlDriver::class] as $driver) {
			$cases[] = [$driver, 0, '40P01', DeadlockException::class];
			$cases[] = [$driver, 0, '40001', SerializationFailureException::class];
		}
		foreach ([SqlsrvDriver::class, PdoSqlsrvDriver::class] as $driver) {
			$cases[] = [$driver, 1205, '40001', DeadlockException::class];
			$cases[] = [$driver, 3960, '40001', SerializationFailureException::class];
		}
		return $cases;
	}


	/**
	 * @dataProvider providePermanentErrors
	 */
	public function testPermanentErrorsAreNotRetryable(string $driverClass, int $code, string $state): void
	{
		$driver = new $driverClass();
		$convert = new ReflectionMethod($driverClass, 'createException');
		$exception = $convert->invoke($driver, 'Duplicate key', $code, $state, 'INSERT INTO example VALUES (1)');
		Assert::type(UniqueConstraintViolationException::class, $exception);
		Assert::false($exception instanceof RetryableException);

		$exception = $convert->invoke($driver, 'Syntax error', 0, '42000', 'INVALID SQL');
		Assert::same(QueryException::class, $exception::class);
		Assert::false($exception instanceof RetryableException);

		$exception = $convert->invoke($driver, 'Connection lost', 0, '08006');
		Assert::false($exception instanceof RetryableException);
	}


	public function providePermanentErrors(): array
	{
		return [
			[MysqliDriver::class, 1062, '23000'],
			[PdoMysqlDriver::class, 1062, '23000'],
			[PgsqlDriver::class, 0, '23505'],
			[PdoPgsqlDriver::class, 0, '23505'],
			[SqlsrvDriver::class, 2627, '23000'],
			[PdoSqlsrvDriver::class, 2627, '23000'],
		];
	}
}


(new RetryableExceptionsTest())->run();
