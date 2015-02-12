<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorWhereTest extends TestCase
{
	/** @var IDriver|Mockery\MockInterface */
	private $driver;

	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');
		$this->parser = new SqlProcessor($this->driver);
	}


	public function testAssoc()
	{
		$this->driver->shouldReceive('convertToSql')->once()->with('a', IDriver::TYPE_IDENTIFIER)->andReturn('A');
		$this->driver->shouldReceive('convertToSql')->once()->with('b.c', IDriver::TYPE_IDENTIFIER)->andReturn('BC');
		$this->driver->shouldReceive('convertToSql')->once()->with('d', IDriver::TYPE_IDENTIFIER)->andReturn('D');
		$this->driver->shouldReceive('convertToSql')->once()->with('e', IDriver::TYPE_IDENTIFIER)->andReturn('E');
		$this->driver->shouldReceive('convertToSql')->once()->with('f', IDriver::TYPE_IDENTIFIER)->andReturn('F');

		$this->driver->shouldReceive('convertToSql')->once()->with(1, IDriver::TYPE_STRING)->andReturn("'1'");
		$this->driver->shouldReceive('convertToSql')->twice()->with('a', IDriver::TYPE_STRING)->andReturn("'a'");

		Assert::same(
			'A = 1 AND BC = 2 AND D IS NULL AND E IN (\'1\', \'a\') AND F IN (1, \'a\')',
			$this->parser->processModifier('and', [
				'a%i' => '1',
				'b.c' => 2,
				'd%s?' => NULL,
				'e%s[]' => ['1', 'a'],
				'f%any[]' => [1, 'a'],
			])
		);
	}


	public function testComplex()
	{
		$this->driver->shouldReceive('convertToSql')->times(3)->with('a', IDriver::TYPE_IDENTIFIER)->andReturn('a');
		$this->driver->shouldReceive('convertToSql')->times(3)->with('b', IDriver::TYPE_IDENTIFIER)->andReturn('b');

		Assert::same(
			'(a = 1 AND b IS NULL) OR a = 2 OR (a IS NULL AND b = 1) OR b = 3',
			$this->parser->processModifier('or', [
				['%and', ['a%i?' => 1, 'b%i?' => NULL]],
				'a' => 2,
				['%and', ['a%i?' => NULL, 'b%i?' => 1]],
				'b' => 3,
			])
		);
	}


	public function testEmptyConds()
	{
		Assert::same(
			'1=1',
			$this->parser->processModifier('and', [])
		);

		Assert::same(
			'1=1',
			$this->parser->processModifier('or', [])
		);
	}

}

$test = new SqlProcessorWhereTest();
$test->run();
