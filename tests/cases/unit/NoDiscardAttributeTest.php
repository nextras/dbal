<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

require_once __DIR__ . '/../../bootstrap.php';

use Nextras\Dbal\Connection;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Result\Row;
use ReflectionMethod;
use Tester\Assert;


class NoDiscardAttributeTest extends TestCase
{
	public function testConnectionInterfaceMethodsHaveNoDiscard(): void
	{
		$this->assertMethodHasNoDiscard(IConnection::class, 'getDriver');
		$this->assertMethodHasNoDiscard(IConnection::class, 'getConfig');
		$this->assertMethodHasNoDiscard(IConnection::class, 'getLastInsertedId');
		$this->assertMethodHasNoDiscard(IConnection::class, 'getAffectedRows');
		$this->assertMethodHasNoDiscard(IConnection::class, 'getPlatform');
		$this->assertMethodHasNoDiscard(IConnection::class, 'createQueryBuilder');
		$this->assertMethodHasNoDiscard(IConnection::class, 'getTransactionNestedIndex');
	}


	public function testConnectionMethodsHaveNoDiscard(): void
	{
		$this->assertMethodHasNoDiscard(Connection::class, 'getDriver');
		$this->assertMethodHasNoDiscard(Connection::class, 'getConfig');
		$this->assertMethodHasNoDiscard(Connection::class, 'getLastInsertedId');
		$this->assertMethodHasNoDiscard(Connection::class, 'getAffectedRows');
		$this->assertMethodHasNoDiscard(Connection::class, 'getPlatform');
		$this->assertMethodHasNoDiscard(Connection::class, 'createQueryBuilder');
		$this->assertMethodHasNoDiscard(Connection::class, 'getTransactionNestedIndex');
	}


	public function testPlatformInterfaceMethodsHaveNoDiscard(): void
	{
		$this->assertMethodHasNoDiscard(IPlatform::class, 'getName');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'getTables');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'getColumns');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'getForeignKeys');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'getPrimarySequenceName');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatString');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatStringLike');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatJson');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatBool');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatIdentifier');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatDateTime');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatLocalDateTime');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatLocalDate');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatDateInterval');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatBlob');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'formatLimitOffset');
		$this->assertMethodHasNoDiscard(IPlatform::class, 'createMultiQueryParser');
	}


	public function testResultMethodsHaveNoDiscard(): void
	{
		$this->assertMethodHasNoDiscard(Result::class, 'getAdapter');
		$this->assertMethodHasNoDiscard(Result::class, 'fetchField');
		$this->assertMethodHasNoDiscard(Result::class, 'fetchAll');
		$this->assertMethodHasNoDiscard(Result::class, 'fetchPairs');
		$this->assertMethodHasNoDiscard(Result::class, 'getColumns');
	}


	public function testRowAndFqnMethodsHaveNoDiscard(): void
	{
		$this->assertMethodHasNoDiscard(Row::class, 'toArray');
		$this->assertMethodHasNoDiscard(Row::class, 'getNthField');
		$this->assertMethodHasNoDiscard(Fqn::class, 'getUnescaped');
	}


	private function assertMethodHasNoDiscard(string $className, string $methodName): void
	{
		$method = new ReflectionMethod($className, $methodName);
		Assert::same([\NoDiscard::class], array_map(
			static fn($attribute): string => $attribute->getName(),
			$method->getAttributes(),
		));
	}
}


$test = new NoDiscardAttributeTest();
$test->run();
