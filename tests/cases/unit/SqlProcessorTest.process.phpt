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
		$this->parser = Mockery::mock('Nextras\Dbal\SqlProcessor[processModifier]', [$this->driver]);
	}


	public function testPatternAndCallback()
	{
		$this->parser->shouldReceive('processModifier')->once()->globally()->ordered()->with('a', 'A')->andReturn('AA');
		$this->parser->shouldReceive('processModifier')->once()->globally()->ordered()->with('?b', 'B')->andReturn('BB');
		$this->parser->shouldReceive('processModifier')->once()->globally()->ordered()->with('c[]', 'C')->andReturn('CC');
		$this->parser->shouldReceive('processModifier')->once()->globally()->ordered()->with('?d[]', 'D')->andReturn('DD');
		$this->driver->shouldReceive('convertToSql')->once()->globally()->ordered()->with('e', IDriver::TYPE_IDENTIFIER)->andReturn('EE');
		$this->driver->shouldReceive('convertToSql')->once()->globally()->ordered()->with('f.f.f', IDriver::TYPE_IDENTIFIER)->andReturn('FF');

		Assert::same(
			'AA BB CC DD EE FF [1]',
			$this->parser->process([
				'%a %?b %c[] %?d[] [e] [f.f.f] [1]',
				'A', 'B', 'C', 'D',
			])
		);
	}


	public function testMultipleFragments()
	{
		$this->parser->shouldReceive('processModifier')->times(3)->andReturnUsing(function($type, $value) {
			return $type . $value;
		});

		Assert::same(
			'A i1 i2 B C i3 D E',
			$this->parser->process(['A %i %i B', 1, 2, 'C %i D', 3, 'E'])
		);
	}


	public function testEscape()
	{
		Assert::same(
			'SELECT DATE_FORMAT(publishedDate, "%Y") AS year FROM foo',
			$this->parser->process(['SELECT DATE_FORMAT(publishedDate, "%%Y") AS year FROM foo'])
		);
	}


	public function testWrongArguments()
	{
		Assert::throws(function() {
			$this->parser->process([123]);
		}, 'Nextras\Dbal\InvalidArgumentException', 'Query fragment must be string.');

		Assert::throws(function() {
			$this->parser->process([new \stdClass()]);
		}, 'Nextras\Dbal\InvalidArgumentException', 'Query fragment must be string.');

		Assert::throws(function() {
			$this->parser->process(['A %xxx']);
		}, 'Nextras\Dbal\InvalidArgumentException', 'Missing query parameter for modifier %xxx.');

		Assert::throws(function() {
			$this->parser->shouldReceive('processModifier')->once()->with('xxx', 1)->andReturn('i1');
			$this->parser->process(['A %xxx B', 1, 2]);
		}, 'Nextras\Dbal\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \'A %xxx B\'.');

		Assert::throws(function() {
			$this->parser->shouldReceive('processModifier')->once()->with('xxx', 1)->andReturn('i1');
			$this->parser->process(['A %xxx B', 1, 'C', 2]);
		}, 'Nextras\Dbal\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \'C\'.');

		Assert::throws(function() {
			$this->parser->shouldReceive('processModifier')->once()->with('xxx', 1)->andReturn('i1');
			$this->parser->process(['A %xxx B', 1, 'C', 'D']);
		}, 'Nextras\Dbal\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \'C\'.');
	}

}

$test = new SqlProcessorProcessTest();
$test->run();
