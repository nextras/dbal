<?php

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorValuesTest extends TestCase
{
	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$driver = \Mockery::mock('Nextras\Dbal\Drivers\IDriver');
		$driver->shouldReceive('getTokenRegexp')->andReturn('');
		$driver->shouldReceive('convertToSql')->with('id', IDriver::TYPE_IDENTIFIER)->andReturn('id');
		$driver->shouldReceive('convertToSql')->with("'foo'", IDriver::TYPE_STRING)->andReturn("'\\'foo\\''");
		$driver->shouldReceive('convertToSql')->with('title', IDriver::TYPE_IDENTIFIER)->andReturn('title');
		$driver->shouldReceive('convertToSql')->with('foo', IDriver::TYPE_IDENTIFIER)->andReturn('foo');
		$driver->shouldReceive('convertToSql')->with(2, IDriver::TYPE_STRING)->andReturn("'2'");
		$this->parser = new SqlProcessor($driver);
	}


	public function testArray()
	{
		Assert::same(
			"INTSERT INTO test (id, title, foo) VALUES (1, '\\'foo\\'', '2')",
			$this->convert('INTSERT INTO test %values', [
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

$test = new SqlProcessorValuesTest();
$test->run();
