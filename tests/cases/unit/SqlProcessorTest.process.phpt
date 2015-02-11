<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorProcessTest extends TestCase
{
	/** @var IDriver|Mockery\MockInterface */
	private $driver;

	/** @var SqlProcessor|Mockery\MockInterface */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');
		$this->parser = Mockery::mock('Nextras\Dbal\SqlProcessor[processValue]', [$this->driver])
			->shouldAllowMockingProtectedMethods();
	}


	public function testPatternAndCallback()
	{
		$this->parser->shouldReceive('processValue')->once()->globally()->ordered()->with('A', 'a')->andReturn('AA');
		$this->parser->shouldReceive('processValue')->once()->globally()->ordered()->with('B', 'b?')->andReturn('BB');
		$this->parser->shouldReceive('processValue')->once()->globally()->ordered()->with('C', 'c[]')->andReturn('CC');
		$this->parser->shouldReceive('processValue')->once()->globally()->ordered()->with('D', 'd?[]')->andReturn('DD');
		$this->driver->shouldReceive('convertToSql')->once()->globally()->ordered()->with('e', IDriver::TYPE_IDENTIFIER)->andReturn('EE');
		$this->driver->shouldReceive('convertToSql')->once()->globally()->ordered()->with('f.f.f', IDriver::TYPE_IDENTIFIER)->andReturn('FF');

		Assert::same(
			'AA BB CC DD EE FF [1]',
			$this->parser->process([
				'%a %b? %c[] %d?[] [e] [f.f.f] [1]',
				'A', 'B', 'C', 'D',
			])
		);
	}


	public function testMultipleFragments()
	{
		$this->parser->shouldReceive('processValue')->times(3)->andReturnUsing(function($value, $type) {
			return $type . $value;
		});

		Assert::same(
			'A i1 i2 B C i3 D E',
			$this->parser->process(['A %i %i B', 1, 2, 'C %i D', 3, 'E'])
		);
	}


	public function testWrongArguments()
	{
		Assert::throws(function() {
			$this->parser->process([123]);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Query fragment must be string.');

		Assert::throws(function() {
			$this->parser->process([new \stdClass()]);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Query fragment must be string.');

		Assert::throws(function() {
			$this->parser->process(['A %xxx']);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Missing query parameter for modifier %xxx.');

		Assert::throws(function() {
			$this->parser->shouldReceive('processValue')->once()->with(1, 'xxx')->andReturn('i1');
			$this->parser->process(['A %xxx B', 1, 2]);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \'A %xxx B\'.');

		Assert::throws(function() {
			$this->parser->shouldReceive('processValue')->once()->with(1, 'xxx')->andReturn('i1');
			$this->parser->process(['A %xxx B', 1, 'C', 2]);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \'C\'.');

		Assert::throws(function() {
			$this->parser->shouldReceive('processValue')->once()->with(1, 'xxx')->andReturn('i1');
			$this->parser->process(['A %xxx B', 1, 'C', 'D']);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \'C\'.');
	}

}

$test = new SqlProcessorProcessTest();
$test->run();
