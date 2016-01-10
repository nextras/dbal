<?php

/**
 * @testCase
 */

namespace NextrasTests\Dbal;

use Mockery;
use Mockery\MockInterface;
use Nette\Caching\IStorage;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Platforms\CachedPlatform;
use Nextras\Dbal\Platforms\IPlatform;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class CachedPlatformTest extends TestCase
{
	/** @var CachedPlatform */
	private $platform;

	/** @var IStorage|MockInterface */
	private $storageMock;

	/** @var IPlatform|MockInterface */
	private $platformMock;


	public function setUp()
	{
		parent::setUp();
		$this->storageMock = Mockery::mock(IStorage::class);
		$this->platformMock = Mockery::mock(IPlatform::class);
		$connection = Mockery::mock(Connection::class)->makePartial();
		$connection->shouldReceive('getConfig')->once()->andReturn('config');
		$connection->shouldReceive('getPlatform')->once()->andReturn($this->platformMock);
		$this->platform = new CachedPlatform($connection, $this->storageMock);
	}


	public function testCachedColumn()
	{
		$expectedCols = ['one', 'two'];
		$this->storageMock
			->shouldReceive('read')
			->with("nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x000985fd27fe963fac675ad2583368ec15")
			->once()
			->andReturn($expectedCols);

		$cols = $this->platform->getColumns('foo');
		Assert::same($expectedCols, $cols);

		$this->storageMock->shouldReceive('clean');
		$this->platform->clearCache();
	}


	public function testQueryColumn()
	{
		$expectedCols = ['one', 'two'];
		$this->storageMock
			->shouldReceive('read')
			->with("nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x000985fd27fe963fac675ad2583368ec15")
			->once()
			->andReturnNull();
		$this->storageMock
			->shouldReceive('lock')
			->with("nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x000985fd27fe963fac675ad2583368ec15")
			->once();
		$this->storageMock
			->shouldReceive('write')
			->with(
				"nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x000985fd27fe963fac675ad2583368ec15",
				$expectedCols,
				[]
			)
			->once();
		$this->platformMock->shouldReceive('getColumns')->with('foo')->once()->andReturn($expectedCols);

		$cols = $this->platform->getColumns('foo');
		Assert::same($expectedCols, $cols);
	}


	public function testQueryTables()
	{
		$expectedTables = ['one', 'two'];
		$this->storageMock
			->shouldReceive('read')
			->with("nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x009ab2ec7ea4a2041306f7bdf150fcd453")
			->once()
			->andReturnNull();
		$this->storageMock
			->shouldReceive('lock')
			->with("nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x009ab2ec7ea4a2041306f7bdf150fcd453")
			->once();
		$this->storageMock
			->shouldReceive('write')
			->with(
				"nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x009ab2ec7ea4a2041306f7bdf150fcd453",
				$expectedTables,
				[]
			)
			->once();
		$this->platformMock->shouldReceive('getTables')->once()->andReturn($expectedTables);

		$cols = $this->platform->getTables();
		Assert::same($expectedTables, $cols);
	}


	public function testQueryFk()
	{
		$expectedFk = ['one', 'two'];
		$this->storageMock
			->shouldReceive('read')
			->with("nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x00d2838f5cf9b11857f7b84b4f490b4227")
			->once()
			->andReturnNull();
		$this->storageMock
			->shouldReceive('lock')
			->with("nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x00d2838f5cf9b11857f7b84b4f490b4227")
			->once();
		$this->storageMock
			->shouldReceive('write')
			->with(
				"nextras.dbal.platform.b5ad707c2b9f71ed843ba3004e50b37d\x00d2838f5cf9b11857f7b84b4f490b4227",
				$expectedFk,
				[]
			)
			->once();
		$this->platformMock->shouldReceive('getForeignKeys')->with('foo')->once()->andReturn($expectedFk);

		$cols = $this->platform->getForeignKeys('foo');
		Assert::same($expectedFk, $cols);
	}
}


$test = new CachedPlatformTest();
$test->run();
