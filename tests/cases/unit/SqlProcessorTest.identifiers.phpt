<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery\MockInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorIdentifiersTest extends TestCase
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


	public function testBasic()
	{
		$this->driver->shouldReceive('convertToSql')->once()->with('a', IDriver::TYPE_IDENTIFIER)->andReturn('`a`');
		$this->driver->shouldReceive('convertToSql')->once()->with('b.c', IDriver::TYPE_IDENTIFIER)->andReturn('`b`.`c`');
		$this->driver->shouldReceive('convertToSql')->once()->with('d.e', IDriver::TYPE_IDENTIFIER)->andReturn('`d`.`e`');

		Assert::same(
			'SELECT `a`, `b`.`c` FROM `d`.`e`',
			$this->parser->process(['SELECT [a], [b.c] FROM [d.e]'])
		);
	}

}

$test = new SqlProcessorIdentifiersTest();
$test->run();
