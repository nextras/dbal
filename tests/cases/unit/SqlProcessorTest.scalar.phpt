<?php

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorScalarTest extends TestCase
{
	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$driver = \Mockery::mock('Nextras\Dbal\Drivers\IDriver');
		$driver->shouldReceive('getTokenRegexp')->andReturn('');
		$driver->shouldReceive('convertToSql')->with("'foo'", IDriver::TYPE_STRING)->andReturn("'\\'foo\\''");
		$driver->shouldReceive('convertToSql')->with(10, IDriver::TYPE_BOOL)->andReturn('1');
		$this->parser = new SqlProcessor($driver);
	}


	public function testArray()
	{
		Assert::same(
			'SELECT FROM test WHERE id = 10',
			$this->convert('SELECT FROM test WHERE id = %i', '010')
		);

		Assert::same(
			'SELECT FROM test WHERE id = NULL',
			$this->convert('SELECT FROM test WHERE id = %i?', NULL)
		);

		Assert::same(
			"SELECT FROM test WHERE id = '\\'foo\\''",
			$this->convert('SELECT FROM test WHERE id = %s', "'foo'")
		);

		Assert::same(
			'SELECT FROM test WHERE is_blocked = 1',
			$this->convert('SELECT FROM test WHERE is_blocked = %b', 10) // truethy value
		);

		Assert::same(
			'SELECT FROM test WHERE price = 1.323',
			$this->convert('SELECT FROM test WHERE price = %f', 1.323)
		);

		Assert::same(
			'SELECT FROM test WHERE price = 1.323',
			$this->convert('SELECT FROM test WHERE price = %f', '01.3230')
		);

		Assert::throws(function() {
			$this->convert('SELECT FROM test WHERE id = %i', NULL);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', "NULL value not allowed in '%i' modifier. Use '%i?' modifier.");
	}


	private function convert($sql)
	{
		return $this->parser->process(func_get_args());
	}

}

$test = new SqlProcessorScalarTest();
$test->run();
