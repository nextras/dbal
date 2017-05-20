<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;

use Mockery;
use Mockery\MockInterface;
use Nette\Caching\Cache;
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
		$connection->shouldReceive('getPlatform')->once()->andReturn($this->platformMock);
		$this->platform = new CachedPlatform($connection, new Cache($this->storageMock));
	}


	public function testCachedColumn()
	{
		$expectedCols = ['one', 'two'];
		$this->storageMock->shouldReceive('read')->with("\x0005ea2f805e9af249b1ac88227bef0153")->once()->andReturn($expectedCols);

		$cols = $this->platform->getColumns('foo');
		Assert::same($expectedCols, $cols);

		$this->storageMock->shouldReceive('clean');
		$this->platform->clearCache();
	}


	public function testQueryColumn()
	{
		$expectedCols = ['one', 'two'];
		$this->storageMock->shouldReceive('read')->with("\x0005ea2f805e9af249b1ac88227bef0153")->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with("\x0005ea2f805e9af249b1ac88227bef0153")->once();
		$this->storageMock->shouldReceive('write')->with("\x0005ea2f805e9af249b1ac88227bef0153", $expectedCols, [])->once();
		$this->platformMock->shouldReceive('getColumns')->with('foo')->once()->andReturn($expectedCols);

		$cols = $this->platform->getColumns('foo');
		Assert::same($expectedCols, $cols);
	}


	public function testQueryTables()
	{
		$expectedTables = ['one', 'two'];
		$this->storageMock->shouldReceive('read')->with("\x009ab2ec7ea4a2041306f7bdf150fcd453")->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with("\x009ab2ec7ea4a2041306f7bdf150fcd453")->once();
		$this->storageMock->shouldReceive('write')->with("\x009ab2ec7ea4a2041306f7bdf150fcd453", $expectedTables, [])->once();
		$this->platformMock->shouldReceive('getTables')->once()->andReturn($expectedTables);

		$cols = $this->platform->getTables();
		Assert::same($expectedTables, $cols);
	}


	public function testQueryFk()
	{
		$expectedFk = ['one', 'two'];
		$this->storageMock->shouldReceive('read')->with("\x00863afe09d043892931f27823c1905607")->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with("\x00863afe09d043892931f27823c1905607")->once();
		$this->storageMock->shouldReceive('write')->with("\x00863afe09d043892931f27823c1905607", $expectedFk, [])->once();
		$this->platformMock->shouldReceive('getForeignKeys')->with('foo')->once()->andReturn($expectedFk);

		$cols = $this->platform->getForeignKeys('foo');
		Assert::same($expectedFk, $cols);
	}


	public function testQueryPS()
	{
		$expectedPs = 'ps_name';
		$this->storageMock->shouldReceive('read')->with("\x007d7dae355d8345dd9301de988fd0eff7")->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with("\x007d7dae355d8345dd9301de988fd0eff7")->once();
		$this->storageMock->shouldReceive('write')->with("\x007d7dae355d8345dd9301de988fd0eff7", $expectedPs, [])->once();
		$this->platformMock->shouldReceive('getPrimarySequenceName')->with('foo')->once()->andReturn($expectedPs);

		$cols = $this->platform->getPrimarySequenceName('foo');
		Assert::same($expectedPs, $cols);
	}


	public function testName()
	{
		$this->platformMock->shouldReceive('getName')->once()->andReturn('foo');
		Assert::same('foo', $this->platform->getName());
	}
}


$test = new CachedPlatformTest();
$test->run();
