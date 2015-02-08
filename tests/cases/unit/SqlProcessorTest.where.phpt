<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery\MockInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorWhereTest extends TestCase
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


	public function testWhereAnd()
	{
		$this->driver->shouldReceive('convertToSql')->once()->with('id', IDriver::TYPE_IDENTIFIER)->andReturn('id');
		$this->driver->shouldReceive('convertToSql')->once()->with('foo', IDriver::TYPE_IDENTIFIER)->andReturn('foo');
		$this->driver->shouldReceive('convertToSql')->once()->with('bar', IDriver::TYPE_IDENTIFIER)->andReturn('bar');
		$this->driver->shouldReceive('convertToSql')->once()->with('baz', IDriver::TYPE_IDENTIFIER)->andReturn('baz');
		$this->driver->shouldReceive('convertToSql')->once()->with('bax', IDriver::TYPE_IDENTIFIER)->andReturn('bax');

		$this->driver->shouldReceive('convertToSql')->once()->with(1, IDriver::TYPE_STRING)->andReturn("'1'");
		$this->driver->shouldReceive('convertToSql')->twice()->with('a', IDriver::TYPE_STRING)->andReturn("'a'");

		Assert::same(
			"SELECT 1 FROM foo WHERE id = 1 AND foo = 2 AND bar IS NULL AND baz IN ('1', 'a') AND bax IN (1, 'a')",
			$this->convert('SELECT 1 FROM foo WHERE %and', [
				'id%i' => '1',
				'foo' => 2,
				'bar%s?' => NULL,
				'baz%s[]' => [1, 'a'],
				'bax%any[]' => [1, 'a'],
			])
		);
	}


	public function testWhereOrNested()
	{
		$this->driver->shouldReceive('convertToSql')->twice()->with('a', IDriver::TYPE_IDENTIFIER)->andReturn('a');
		$this->driver->shouldReceive('convertToSql')->twice()->with('b', IDriver::TYPE_IDENTIFIER)->andReturn('b');

		Assert::same(
			"SELECT 1 FROM foo WHERE (a = 1 AND b IS NULL) OR (a IS NULL AND b = 1)",
			$this->convert('SELECT 1 FROM foo WHERE %or', [
				['%and', ['a%i?' => 1, 'b%i?' => NULL]],
				['%and', ['a%i?' => NULL, 'b%i?' => 1]],
			])
		);
	}


	public function testEmptyConds()
	{
		Assert::same(
			'SELECT 1 FROM foo WHERE 1=1',
			$this->convert('SELECT 1 FROM foo WHERE %and', [])
		);
	}


	private function convert($sql)
	{
		return $this->parser->process(func_get_args());
	}

}

$test = new SqlProcessorWhereTest();
$test->run();
