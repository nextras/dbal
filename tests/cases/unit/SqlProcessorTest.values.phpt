<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery\MockInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorValuesTest extends TestCase
{
	/** @var MockInterface */
	private $driver;

	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = \Mockery::mock('Nextras\Dbal\Drivers\IDriver');
		$this->parser = new SqlProcessor($this->driver);
	}


	public function testArray()
	{
		$this->driver->shouldReceive('convertToSql')->once()->with('id', IDriver::TYPE_IDENTIFIER)->andReturn('id');
		$this->driver->shouldReceive('convertToSql')->once()->with("'foo'", IDriver::TYPE_STRING)->andReturn("'\\'foo\\''");
		$this->driver->shouldReceive('convertToSql')->once()->with('title', IDriver::TYPE_IDENTIFIER)->andReturn('title');
		$this->driver->shouldReceive('convertToSql')->once()->with('foo', IDriver::TYPE_IDENTIFIER)->andReturn('foo');

		Assert::same(
			"INSERT INTO test (id, title, foo) VALUES (1, '\\'foo\\'', 2)",
			$this->convert('INSERT INTO test %values', [
				'id%i' => 1,
				'title%s' => "'foo'",
				'foo' => 2,
			])
		);
	}


	public function testMultiInsert()
	{
		$this->driver->shouldReceive('convertToSql')->once()->with('id', IDriver::TYPE_IDENTIFIER)->andReturn('id');
		$this->driver->shouldReceive('convertToSql')->once()->with('title', IDriver::TYPE_IDENTIFIER)->andReturn('title');
		$this->driver->shouldReceive('convertToSql')->once()->with('foo', IDriver::TYPE_IDENTIFIER)->andReturn('foo');

		$this->driver->shouldReceive('convertToSql')->once()->with("'foo'", IDriver::TYPE_STRING)->andReturn("'\\'foo\\''");
		$this->driver->shouldReceive('convertToSql')->once()->with("'foo2'", IDriver::TYPE_STRING)->andReturn("'\\'foo2\\''");

		Assert::same(
			"INSERT INTO test (id, title, foo) VALUES (1, '\\'foo\\'', 2), (2, '\\'foo2\\'', 3)",
			$this->convert('INSERT INTO test %values[]', [
				[
					'id%i' => 1,
					'title%s' => "'foo'",
					'foo' => 2,
				],
				[
					'id%i' => 2,
					'title%s' => "'foo2'",
					'foo' => 3,
				],
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
