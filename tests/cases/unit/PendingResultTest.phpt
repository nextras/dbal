<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;


use Mockery;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Result\PendingResult;
use Nextras\Dbal\Result\Result;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class PendingResultTest extends TestCase
{
	public function testResolved(): void
	{
		$result = $this->createResult();
		$pendingResult = PendingResult::createResolved($result);

		Assert::true($pendingResult->isResolved());
		Assert::same($result, $pendingResult->getResult());
		Assert::same($result, $pendingResult->getResult());
	}


	public function testResolverIsCalledOnce(): void
	{
		$result = $this->createResult();
		$calls = 0;
		$pendingResult = PendingResult::createPending(function () use ($result, &$calls): Result {
			$calls++;
			return $result;
		});

		Assert::false($pendingResult->isResolved());
		Assert::same(0, $calls);
		Assert::same($result, $pendingResult->getResult());
		Assert::true($pendingResult->isResolved());
		Assert::same($result, $pendingResult->getResult());
		Assert::same(1, $calls);
	}


	public function testCollectStoresFailure(): void
	{
		$failure = new DriverException('Query failed');
		$calls = 0;
		$pendingResult = PendingResult::createPending(function () use ($failure, &$calls): Result {
			$calls++;
			throw $failure;
		});

		Assert::same($failure, $pendingResult->collect());
		Assert::same($failure, $pendingResult->collect());
		Assert::true($pendingResult->isResolved());
		Assert::same($failure, Assert::exception(fn() => $pendingResult->getResult(), DriverException::class));
		Assert::same($failure, Assert::exception(fn() => $pendingResult->getResult(), DriverException::class));
		Assert::same(1, $calls);
	}


	public function testCollectReturnsNoFailureOnceRead(): void
	{
		$failure = new DriverException('Query failed');
		$pendingResult = PendingResult::createFailed($failure);
		Assert::same($failure, $pendingResult->collect());
		Assert::exception(fn() => $pendingResult->getResult(), DriverException::class);
		Assert::null($pendingResult->collect());
	}


	public function testReentrantGetResult(): void
	{
		$result = $this->createResult();
		$pendingResult = null;
		$pendingResult = PendingResult::createPending(function () use ($result, &$pendingResult): Result {
			Assert::false($pendingResult->isResolved());
			Assert::exception(fn() => $pendingResult->getResult(), InvalidStateException::class);
			return $result;
		});

		Assert::same($result, $pendingResult->getResult());
	}


	private function createResult(): Result
	{
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getNormalizers')->andReturn([]);
		return new Result($adapter);
	}
}


(new PendingResultTest())->run();
