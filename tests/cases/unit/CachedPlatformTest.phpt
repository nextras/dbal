<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;


use Mockery;
use Mockery\MockInterface;
use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;
use Nextras\Dbal\Bridges\NetteCaching\CachedPlatform;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Dbal\Platforms\IPlatform;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class CachedPlatformTest extends TestCase
{
	private CachedPlatform $cache;

	/** @var MockInterface */
	private $platformMock;


	public function setUp(): void
	{
		parent::setUp();
		$this->platformMock = Mockery::mock(IPlatform::class);
		$this->cache = new CachedPlatform($this->platformMock, new Cache(new FileStorage(TEMP_DIR)));
	}


	public function testColumns(): void
	{
		$column = new Column(
			name: 'bar',
			type: 'type',
			size: 128,
			default: null,
			isPrimary: true,
			isAutoincrement: false,
			isUnsigned: false,
			isNullable: true,
			meta: ['sequence' => 'a.b'],
		);

		$this->platformMock->shouldReceive('getColumns')->with('foo', null)->once()->andReturn([clone $column]);

		Assert::equal([clone $column], $this->cache->getColumns('foo'));
		Assert::equal([clone $column], $this->cache->getColumns('foo'));
	}


	public function testTables(): void
	{
		$table = new Table(
			fqnName: new Fqn('one', 'two'),
			isView: false,
		);
		$this->platformMock->shouldReceive('getTables')->once()->andReturn([clone $table]);

		Assert::equal([clone $table], $this->cache->getTables());
		Assert::equal([clone $table], $this->cache->getTables());
	}


	public function testForeignKeys(): void
	{
		$fk = new ForeignKey(
			fqnName: new Fqn('one', 'two'),
			column: 'col',
			refTable: new Fqn('three', 'four'),
			refColumn: 'refCol',
		);
		$this->platformMock->shouldReceive('getForeignKeys')->with('foo', null)->once()->andReturn([clone $fk]);

		Assert::equal([clone $fk], $this->cache->getForeignKeys('foo'));
		Assert::equal([clone $fk], $this->cache->getForeignKeys('foo'));
	}


	public function testQueryPrimarySequence(): void
	{
		$this->platformMock->shouldReceive('getPrimarySequenceName')->with('foo', null)->once()
			->andReturn('ps_name');

		Assert::equal('ps_name', $this->cache->getPrimarySequenceName('foo'));
		Assert::equal('ps_name', $this->cache->getPrimarySequenceName('foo'));
	}


	public function testName(): void
	{
		$this->platformMock->shouldReceive('getName')->twice()->andReturn('foo');
		Assert::same('foo', $this->cache->getName());
		Assert::same('foo', $this->cache->getName()); // no cache
	}
}


$test = new CachedPlatformTest();
$test->run();
