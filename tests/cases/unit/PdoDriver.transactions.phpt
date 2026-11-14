<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;


use Mockery;
use Nextras\Dbal\Drivers\Exception\DeadlockException;
use Nextras\Dbal\Drivers\Pdo\PdoDriver;
use Nextras\Dbal\Drivers\PdoMysql\PdoMysqlDriver;
use Nextras\Dbal\ILogger;
use PDO;
use PDOException;
use ReflectionProperty;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class PdoDriverTransactionsTest extends TestCase
{
	/**
	 * @dataProvider provideCommands
	 */
	public function testFalseResultIsConverted(string $driverMethod, string $pdoMethod): void
	{
		$pdo = Mockery::mock(PDO::class);
		$pdo->shouldReceive($pdoMethod)->once()->andReturn(false);
		$pdo->shouldReceive('errorInfo')->once()->andReturn(['40001', 1213, 'Deadlock']);
		$driver = $this->createDriver($pdo);

		$exception = Assert::exception(fn() => $driver->$driverMethod(), DeadlockException::class);
		Assert::same(1213, $exception->getErrorCode());
		Assert::same('40001', $exception->getErrorSqlState());
		Assert::null($exception->getPrevious());
	}


	/**
	 * @dataProvider provideCommands
	 */
	public function testNativeExceptionIsConvertedAndPreserved(string $driverMethod, string $pdoMethod): void
	{
		$nativeException = new PDOException('Deadlock');
		$nativeException->errorInfo = ['40001', 1213, 'Deadlock'];
		$pdo = Mockery::mock(PDO::class);
		$pdo->shouldReceive($pdoMethod)->once()->andThrow($nativeException);
		$driver = $this->createDriver($pdo);

		$exception = Assert::exception(fn() => $driver->$driverMethod(), DeadlockException::class);
		Assert::same(1213, $exception->getErrorCode());
		Assert::same('40001', $exception->getErrorSqlState());
		Assert::same($nativeException, $exception->getPrevious());
	}


	public function provideCommands(): array
	{
		return [
			['beginTransaction', 'beginTransaction'],
			['commitTransaction', 'commit'],
			['rollbackTransaction', 'rollBack'],
		];
	}


	private function createDriver(PDO $pdo): PdoMysqlDriver
	{
		$driver = new PdoMysqlDriver();
		(new ReflectionProperty(PdoDriver::class, 'connection'))->setValue($driver, $pdo);
		(new ReflectionProperty(PdoDriver::class, 'logger'))->setValue($driver, Mockery::mock(ILogger::class));
		return $driver;
	}
}


(new PdoDriverTransactionsTest())->run();
