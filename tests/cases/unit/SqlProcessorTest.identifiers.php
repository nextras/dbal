<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;


use Mockery\MockInterface;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorIdentifiersTest extends TestCase
{
	/** @var IPlatform|MockInterface */
	private $platform;

	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->platform = \Mockery::mock(IPlatform::class);
		$this->parser = new SqlProcessor($this->platform);
	}


	public function testBasic()
	{
		$this->platform->shouldReceive('formatIdentifier')->once()->with('a')->andReturn('`a`');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('b.c')->andReturn('`b`.`c`');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('d.e')->andReturn('`d`.`e`');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('name')->andReturn('`name`');

		Assert::same(
			'SELECT `a`, `b`.`c` FROM `d`.`e` WHERE `name` = ANY(ARRAY[\'Jan\'])',
			$this->parser->process(["SELECT [a], [b.c] FROM [d.e] WHERE [name] = ANY(ARRAY[['Jan']])"]),
		);
	}


	public function testFqn()
	{
		$this->platform->shouldReceive('formatIdentifier')->once()->with('a')->andReturn('`a`');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('b')->andReturn('`b`');

		Assert::same(
			'`a`.`b`',
			$this->parser->process(['%table', new Fqn('b', schema: 'a')]),
		);
	}


	public function testStar()
	{
		$this->platform->shouldReceive('formatIdentifier')->once()->with('a')->andReturn('`a`');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('b.c')->andReturn('`b`.`c`');

		Assert::same(
			'SELECT `a`.*, `b`.`c`.*',
			$this->parser->process(["SELECT [a.*], [b.c.*]"]),
		);
	}
}


$test = new SqlProcessorIdentifiersTest();
$test->run();
