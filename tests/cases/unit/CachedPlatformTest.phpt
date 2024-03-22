<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;

use Mockery;
use Mockery\MockInterface;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nextras\Dbal\Bridges\NetteCaching\CachedPlatform;
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
		$this->platform = new CachedPlatform($this->platformMock, new Cache($this->storageMock));
	}


	public function testCachedColumn()
	{
		$expectedCols = ['one', 'two'];
		$this->storageMock->shouldReceive('read')->with(Mockery::type('string'))->once()->andReturn($expectedCols);

		$cols = $this->platform->getColumns('foo');
		Assert::same($expectedCols, $cols);

		$this->storageMock->shouldReceive('clean');
		$this->platform->clearCache();
	}


	public function testQueryColumn()
	{
		$expectedCols = ['one', 'two'];
		$this->storageMock->shouldReceive('read')->with(Mockery::type('string'))->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with(Mockery::type('string'))->once();
		$this->storageMock->shouldReceive('write')->with(Mockery::type('string'), $expectedCols, [])->once();
		$this->platformMock->shouldReceive('getColumns')->with('foo', null)->once()->andReturn($expectedCols);

		$cols = $this->platform->getColumns('foo');
		Assert::same($expectedCols, $cols);
	}


	public function testQueryTables()
	{
		$expectedTables = ['one', 'two'];
		$this->storageMock->shouldReceive('read')->with(Mockery::type('string'))->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with(Mockery::type('string'))->once();
		$this->storageMock->shouldReceive('write')->with(Mockery::type('string'), $expectedTables, [])->once();
		$this->platformMock->shouldReceive('getTables')->once()->andReturn($expectedTables);

		$cols = $this->platform->getTables();
		Assert::same($expectedTables, $cols);
	}


	public function testQueryFk()
	{
		$expectedFk = ['one', 'two'];
		$this->storageMock->shouldReceive('read')->with(Mockery::type('string'))->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with(Mockery::type('string'))->once();
		$this->storageMock->shouldReceive('write')->with(Mockery::type('string'), $expectedFk, [])->once();
		$this->platformMock->shouldReceive('getForeignKeys')->with('foo', null)->once()->andReturn($expectedFk);

		$cols = $this->platform->getForeignKeys('foo');
		Assert::same($expectedFk, $cols);
	}


	public function testQueryPS()
	{
		$expectedPs = 'ps_name';
		$this->storageMock->shouldReceive('read')->with(Mockery::type('string'))->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with(Mockery::type('string'))->once();
		$this->storageMock->shouldReceive('write')->with(Mockery::type('string'), [$expectedPs], [])->once();
		$this->platformMock->shouldReceive('getPrimarySequenceName')->with('foo', null)->once()->andReturn($expectedPs);

		$cols = $this->platform->getPrimarySequenceName('foo');
		Assert::same($expectedPs, $cols);


		$this->storageMock->shouldReceive('read')->with(Mockery::type('string'))->once()->andReturnNull();
		$this->storageMock->shouldReceive('lock')->with(Mockery::type('string'))->once();
		$this->storageMock->shouldReceive('write')->with(Mockery::type('string'), [null], [])->once();
		$this->platformMock->shouldReceive('getPrimarySequenceName')->with('foo', null)->once()->andReturn(null);

		$cols = $this->platform->getPrimarySequenceName('foo');
		Assert::same(null, $cols);
	}


	public function testName()
	{
		$this->platformMock->shouldReceive('getName')->once()->andReturn('foo');
		Assert::same('foo', $this->platform->getName());
	}
}


$test = new CachedPlatformTest();
$test->run();
