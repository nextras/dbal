<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IDriver;
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
		$driver = \Mockery::mock(IDriver::class);
		$driver->shouldReceive('convertIdentifierToSql')->once()->with('id')->andReturn('id');
		$driver->shouldReceive('convertStringToSql')->once()->with("'foo'")->andReturn("'\\'foo\\''");
		$driver->shouldReceive('convertIdentifierToSql')->once()->with('title')->andReturn('title');
		$driver->shouldReceive('convertIdentifierToSql')->once()->with('foo')->andReturn('foo');
		$this->parser = new SqlProcessor($driver);
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
