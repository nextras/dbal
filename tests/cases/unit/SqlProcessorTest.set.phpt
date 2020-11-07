<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorSetTest extends TestCase
{
	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$platform = \Mockery::mock(IPlatform::class);
		$platform->shouldReceive('formatString')->once()->with("'foo'")->andReturn("'\\'foo\\''");
		$platform->shouldReceive('formatIdentifier')->once()->with('id')->andReturn('id');
		$platform->shouldReceive('formatIdentifier')->once()->with('title')->andReturn('title');
		$platform->shouldReceive('formatIdentifier')->once()->with('foo')->andReturn('foo');
		$this->parser = new SqlProcessor($platform);
	}


	public function testArray()
	{
		Assert::same(
			"UPDATE test SET id = 1, title = '\\'foo\\'', foo = 2",
			$this->convert('UPDATE test SET %set', [
				'id%i' => 1,
				'title%s' => "'foo'",
				'foo' => 2,
			])
		);
	}


	private function convert($sql)
	{
		return $this->parser->process(func_get_args());
	}
}


$test = new SqlProcessorSetTest();
$test->run();
