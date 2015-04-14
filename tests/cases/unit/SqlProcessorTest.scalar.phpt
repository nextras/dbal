<?php

/** @testCase */

namespace NextrasTests\Dbal;

use DateTime;
use Mockery;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use stdClass;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorScalarTest extends TestCase
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


	public function testString()
	{
		$this->driver->shouldReceive('convertToSql')->once()->with('A', IDriver::TYPE_STRING)->andReturn('B');
		Assert::same('B', $this->parser->processModifier('s', 'A'));

		// object with __toString
		$file = new \SplFileInfo('C');
		$this->driver->shouldReceive('convertToSql')->once()->with('C', IDriver::TYPE_STRING)->andReturn('D');
		Assert::same('D', $this->parser->processModifier('s', $file));
	}


	public function testInt()
	{
		Assert::same('123', $this->parser->processModifier('i', 123));
		Assert::same('123', $this->parser->processModifier('i', '123')); // required to support large integers
	}


	public function testFloat()
	{
		Assert::same('-123.4', $this->parser->processModifier('f', -123.4));
		Assert::same('-123.0', $this->parser->processModifier('f', -123.0));
		Assert::same('0.123', $this->parser->processModifier('f', 0.123));
		Assert::same('1.0e+15', $this->parser->processModifier('f', 1e15));
	}


	public function testBool()
	{
		$this->driver->shouldReceive('convertToSql')->once()->with(TRUE, IDriver::TYPE_BOOL)->andReturn('T');
		Assert::same('T', $this->parser->processModifier('b', TRUE));

		$this->driver->shouldReceive('convertToSql')->once()->with(FALSE, IDriver::TYPE_BOOL)->andReturn('F');
		Assert::same('F', $this->parser->processModifier('b', FALSE));
	}


	public function testDateTime()
	{
		$dt = new DateTime('2012-03-05 12:01');
		$this->driver->shouldReceive('convertToSql')->once()->with($dt, IDriver::TYPE_DATETIME)->andReturn('DT');
		Assert::same('DT', $this->parser->processModifier('dt', $dt));
	}


	public function testDateTimeSimple()
	{
		$dt = new DateTime('2012-03-05 12:01');
		$this->driver->shouldReceive('convertToSql')->once()->with($dt, IDriver::TYPE_DATETIME_SIMPLE)->andReturn('DTS');
		Assert::same('DTS', $this->parser->processModifier('dts', $dt));
	}


	public function testColumn()
	{
		$this->driver->shouldReceive('convertToSql')->once()->with('foo', IDriver::TYPE_IDENTIFIER)->andReturn('FOO');
		Assert::same('FOO', $this->parser->processModifier('column', 'foo'));


		$this->driver->shouldReceive('convertToSql')->once()->with('a', IDriver::TYPE_IDENTIFIER)->andReturn('A');
		$this->driver->shouldReceive('convertToSql')->once()->with('b', IDriver::TYPE_IDENTIFIER)->andReturn('B');
		$this->driver->shouldReceive('convertToSql')->once()->with('c', IDriver::TYPE_IDENTIFIER)->andReturn('C');
		Assert::same('(A, B, C)', $this->parser->processModifier('column[]', ['a', 'b', 'c']));


		Assert::exception(function() {
			// test break to process non-string values
			$this->parser->processModifier('column[]', [1]);
		}, 'Nextras\Dbal\InvalidArgumentException', 'Modifier %column expects value to be string, integer given.');


		Assert::exception(function() {
			$this->parser->processModifier('column', '*');
		}, 'Nextras\Dbal\InvalidArgumentException', "Modifier %column does not allow '*' value, use modifier %column[] instead.");
	}


	public function testAny()
	{
		$dt = new DateTime('2012-03-05 12:01');
		$di = $dt->diff(new DateTime('2012-03-05 08:00'));

		$this->driver->shouldReceive('convertToSql')->once()->with('A', IDriver::TYPE_STRING)->andReturn('B');
		$this->driver->shouldReceive('convertToSql')->once()->with(TRUE, IDriver::TYPE_BOOL)->andReturn('T');
		$this->driver->shouldReceive('convertToSql')->once()->with($dt, IDriver::TYPE_DATETIME)->andReturn('DT');
		Assert::same('B', $this->parser->processModifier('any', 'A'));
		Assert::same('123', $this->parser->processModifier('any', 123));
		Assert::same('123.4', $this->parser->processModifier('any', 123.4));
		Assert::same('T', $this->parser->processModifier('any', TRUE));
		Assert::same('DT', $this->parser->processModifier('any', $dt));
		Assert::same('NULL', $this->parser->processModifier('any', NULL));

		$this->driver->shouldReceive('convertToSql')->once()->with('A', IDriver::TYPE_STRING)->andReturn('B');
		$this->driver->shouldReceive('convertToSql')->once()->with(TRUE, IDriver::TYPE_BOOL)->andReturn('T');
		$this->driver->shouldReceive('convertToSql')->once()->with($dt, IDriver::TYPE_DATETIME)->andReturn('DT');
		$this->driver->shouldReceive('convertToSql')->once()->with($di, IDriver::TYPE_DATE_INTERVAL)->andReturn('DI');
		Assert::same('(B, 123, 123.4, T, DT, DI, NULL)', $this->parser->processModifier('any', ['A', 123, 123.4, TRUE, $dt, $di, NULL]));
	}


	public function testNullable()
	{
		Assert::same('NULL', $this->parser->processModifier('?s', NULL));
		Assert::same('NULL', $this->parser->processModifier('?i', NULL));
		Assert::same('NULL', $this->parser->processModifier('?f', NULL));
		Assert::same('NULL', $this->parser->processModifier('?b', NULL));
		Assert::same('NULL', $this->parser->processModifier('?dt', NULL));
		Assert::same('NULL', $this->parser->processModifier('?dts', NULL));
		Assert::same('NULL', $this->parser->processModifier('?di', NULL));
		Assert::same('NULL', $this->parser->processModifier('any', NULL));
	}


	/**
	 * @dataProvider provideInvalidData
	 */
	public function testInvalid($type, $value, $message)
	{
		Assert::throws(
			function() use ($type, $value) {
				$this->parser->processModifier($type, $value);
			},
			'Nextras\Dbal\InvalidArgumentException', $message
		);
	}


	public function provideInvalidData()
	{
		// object with __toString
		$file = new \SplFileInfo('C');

		return [
			['s', 123, 'Modifier %s expects value to be string, integer given.'],
			['s', 123.0, 'Modifier %s expects value to be string, double given.'],
			['s', TRUE, 'Modifier %s expects value to be string, boolean given.'],
			['s', [], 'Modifier %s does not allow array value, use modifier %s[] instead.'],
			['s', new stdClass(), 'Modifier %s expects value to be string, stdClass given.'],
			['s', NULL, 'Modifier %s does not allow NULL value, use modifier %?s instead.'],

			['?s', 123, 'Modifier %?s expects value to be string, integer given.'],
			['?s', 123.0, 'Modifier %?s expects value to be string, double given.'],
			['?s', TRUE, 'Modifier %?s expects value to be string, boolean given.'],
			['?s', [], 'Modifier %?s does not allow array value, use modifier %?s[] instead.'],
			['?s', new stdClass(), 'Modifier %?s expects value to be string, stdClass given.'],

			['s[]', '123', 'Modifier %s[] expects value to be array, string given.'],
			['s[]', 123, 'Modifier %s[] expects value to be array, integer given.'],
			['s[]', 123.0, 'Modifier %s[] expects value to be array, double given.'],
			['s[]', TRUE, 'Modifier %s[] expects value to be array, boolean given.'],
			['s[]', new stdClass(), 'Modifier %s[] expects value to be array, stdClass given.'],
			['s[]', $file, 'Modifier %s[] expects value to be array, SplFileInfo given.'],
			['s[]', NULL, 'Modifier %s[] expects value to be array, NULL given.'],
			['s[]', [123], 'Modifier %s expects value to be string, integer given.'],
			['s[]', [123.0], 'Modifier %s expects value to be string, double given.'],
			['s[]', [TRUE], 'Modifier %s expects value to be string, boolean given.'],
			['s[]', [[]], 'Modifier %s does not allow array value, use modifier %s[] instead.'],
			['s[]', [new stdClass()], 'Modifier %s expects value to be string, stdClass given.'],
			['s[]', [NULL], 'Modifier %s does not allow NULL value, use modifier %?s instead.'],

			['?s[]', '123', 'Modifier %?s[] expects value to be array, string given.'],
			['?s[]', 123, 'Modifier %?s[] expects value to be array, integer given.'],
			['?s[]', 123.0, 'Modifier %?s[] expects value to be array, double given.'],
			['?s[]', TRUE, 'Modifier %?s[] expects value to be array, boolean given.'],
			['?s[]', new stdClass(), 'Modifier %?s[] expects value to be array, stdClass given.'],
			['?s[]', $file, 'Modifier %?s[] expects value to be array, SplFileInfo given.'],
			['?s[]', NULL, 'Modifier %?s[] expects value to be array, NULL given.'],
			['?s[]', [123], 'Modifier %?s expects value to be string, integer given.'],
			['?s[]', [123.0], 'Modifier %?s expects value to be string, double given.'],
			['?s[]', [TRUE], 'Modifier %?s expects value to be string, boolean given.'],
			['?s[]', [[]], 'Modifier %?s does not allow array value, use modifier %?s[] instead.'],
			['?s[]', [new stdClass()], 'Modifier %?s expects value to be string, stdClass given.'],

			['i', '123x', 'Modifier %i expects value to be int, string given.'],
			['i', '0123', 'Modifier %i expects value to be int, string given.'],
			['i', 123.0, 'Modifier %i expects value to be int, double given.'],
			['i', TRUE, 'Modifier %i expects value to be int, boolean given.'],
			['i', [], 'Modifier %i does not allow array value, use modifier %i[] instead.'],
			['i', new stdClass(), 'Modifier %i expects value to be int, stdClass given.'],
			['i', $file, 'Modifier %i expects value to be int, SplFileInfo given.'],
			['i', NULL, 'Modifier %i does not allow NULL value, use modifier %?i instead.'],

			['?i', '123x', 'Modifier %?i expects value to be int, string given.'],
			['?i', '0123', 'Modifier %?i expects value to be int, string given.'],
			['?i', 123.0, 'Modifier %?i expects value to be int, double given.'],
			['?i', TRUE, 'Modifier %?i expects value to be int, boolean given.'],
			['?i', [], 'Modifier %?i does not allow array value, use modifier %?i[] instead.'],
			['?i', new stdClass(), 'Modifier %?i expects value to be int, stdClass given.'],
			['?i', $file, 'Modifier %?i expects value to be int, SplFileInfo given.'],

			['i[]', '123', 'Modifier %i[] expects value to be array, string given.'],
			['i[]', 123, 'Modifier %i[] expects value to be array, integer given.'],
			['i[]', 123.0, 'Modifier %i[] expects value to be array, double given.'],
			['i[]', TRUE, 'Modifier %i[] expects value to be array, boolean given.'],
			['i[]', new stdClass(), 'Modifier %i[] expects value to be array, stdClass given.'],
			['i[]', $file, 'Modifier %i[] expects value to be array, SplFileInfo given.'],
			['i[]', NULL, 'Modifier %i[] expects value to be array, NULL given.'],
			['i[]', ['123x'], 'Modifier %i expects value to be int, string given.'],
			['i[]', [123.0], 'Modifier %i expects value to be int, double given.'],
			['i[]', [TRUE], 'Modifier %i expects value to be int, boolean given.'],
			['i[]', [[]], 'Modifier %i does not allow array value, use modifier %i[] instead.'],
			['i[]', [new stdClass()], 'Modifier %i expects value to be int, stdClass given.'],
			['i[]', [NULL], 'Modifier %i does not allow NULL value, use modifier %?i instead.'],

			['?i[]', '123', 'Modifier %?i[] expects value to be array, string given.'],
			['?i[]', 123, 'Modifier %?i[] expects value to be array, integer given.'],
			['?i[]', 123.0, 'Modifier %?i[] expects value to be array, double given.'],
			['?i[]', TRUE, 'Modifier %?i[] expects value to be array, boolean given.'],
			['?i[]', new stdClass(), 'Modifier %?i[] expects value to be array, stdClass given.'],
			['?i[]', $file, 'Modifier %?i[] expects value to be array, SplFileInfo given.'],
			['?i[]', NULL, 'Modifier %?i[] expects value to be array, NULL given.'],
			['?i[]', ['123x'], 'Modifier %?i expects value to be int, string given.'],
			['?i[]', ['0123'], 'Modifier %?i expects value to be int, string given.'],
			['?i[]', [123.0], 'Modifier %?i expects value to be int, double given.'],
			['?i[]', [TRUE], 'Modifier %?i expects value to be int, boolean given.'],
			['?i[]', [[]], 'Modifier %?i does not allow array value, use modifier %?i[] instead.'],
			['?i[]', [new stdClass()], 'Modifier %?i expects value to be int, stdClass given.'],

			['f', NAN, 'Modifier %f expects value to be (finite) float, NAN given.'],
			['f', NAN, 'Modifier %f expects value to be (finite) float, NAN given.'],
			['f', +INF, 'Modifier %f expects value to be (finite) float, INF given.'],
			['f', -INF, 'Modifier %f expects value to be (finite) float, -INF given.'],
			['f', '123.4', 'Modifier %f expects value to be (finite) float, string given.'],
			['f', 123, 'Modifier %f expects value to be (finite) float, integer given.'],
			['f', TRUE, 'Modifier %f expects value to be (finite) float, boolean given.'],
			['f', [], 'Modifier %f does not allow array value, use modifier %f[] instead.'],
			['f', new stdClass(), 'Modifier %f expects value to be (finite) float, stdClass given.'],
			['f', $file, 'Modifier %f expects value to be (finite) float, SplFileInfo given.'],
			['f', NULL, 'Modifier %f does not allow NULL value, use modifier %?f instead.'],

			['?f', NAN, 'Modifier %?f expects value to be (finite) float, NAN given.'],
			['?f', NAN, 'Modifier %?f expects value to be (finite) float, NAN given.'],
			['?f', +INF, 'Modifier %?f expects value to be (finite) float, INF given.'],
			['?f', -INF, 'Modifier %?f expects value to be (finite) float, -INF given.'],
			['?f', '123.4', 'Modifier %?f expects value to be (finite) float, string given.'],
			['?f', 123, 'Modifier %?f expects value to be (finite) float, integer given.'],
			['?f', TRUE, 'Modifier %?f expects value to be (finite) float, boolean given.'],
			['?f', [], 'Modifier %?f does not allow array value, use modifier %?f[] instead.'],
			['?f', new stdClass(), 'Modifier %?f expects value to be (finite) float, stdClass given.'],
			['?f', $file, 'Modifier %?f expects value to be (finite) float, SplFileInfo given.'],

			['f[]', '123', 'Modifier %f[] expects value to be array, string given.'],
			['f[]', 123, 'Modifier %f[] expects value to be array, integer given.'],
			['f[]', 123.0, 'Modifier %f[] expects value to be array, double given.'],
			['f[]', TRUE, 'Modifier %f[] expects value to be array, boolean given.'],
			['f[]', new stdClass(), 'Modifier %f[] expects value to be array, stdClass given.'],
			['f[]', $file, 'Modifier %f[] expects value to be array, SplFileInfo given.'],
			['f[]', NULL, 'Modifier %f[] expects value to be array, NULL given.'],
			['f[]', [NAN], 'Modifier %f expects value to be (finite) float, NAN given.'],
			['f[]', [NAN], 'Modifier %f expects value to be (finite) float, NAN given.'],
			['f[]', [+INF], 'Modifier %f expects value to be (finite) float, INF given.'],
			['f[]', [-INF], 'Modifier %f expects value to be (finite) float, -INF given.'],
			['f[]', ['123.4'], 'Modifier %f expects value to be (finite) float, string given.'],
			['f[]', [123], 'Modifier %f expects value to be (finite) float, integer given.'],
			['f[]', [TRUE], 'Modifier %f expects value to be (finite) float, boolean given.'],
			['f[]', [[]], 'Modifier %f does not allow array value, use modifier %f[] instead.'],
			['f[]', [new stdClass()], 'Modifier %f expects value to be (finite) float, stdClass given.'],
			['f[]', [NULL], 'Modifier %f does not allow NULL value, use modifier %?f instead.'],

			['?f[]', '123', 'Modifier %?f[] expects value to be array, string given.'],
			['?f[]', 123, 'Modifier %?f[] expects value to be array, integer given.'],
			['?f[]', 123.0, 'Modifier %?f[] expects value to be array, double given.'],
			['?f[]', TRUE, 'Modifier %?f[] expects value to be array, boolean given.'],
			['?f[]', new stdClass(), 'Modifier %?f[] expects value to be array, stdClass given.'],
			['?f[]', $file, 'Modifier %?f[] expects value to be array, SplFileInfo given.'],
			['?f[]', NULL, 'Modifier %?f[] expects value to be array, NULL given.'],
			['?f[]', [NAN], 'Modifier %?f expects value to be (finite) float, NAN given.'],
			['?f[]', [NAN], 'Modifier %?f expects value to be (finite) float, NAN given.'],
			['?f[]', [+INF], 'Modifier %?f expects value to be (finite) float, INF given.'],
			['?f[]', [-INF], 'Modifier %?f expects value to be (finite) float, -INF given.'],
			['?f[]', ['123.4'], 'Modifier %?f expects value to be (finite) float, string given.'],
			['?f[]', [123], 'Modifier %?f expects value to be (finite) float, integer given.'],
			['?f[]', [TRUE], 'Modifier %?f expects value to be (finite) float, boolean given.'],
			['?f[]', [[]], 'Modifier %?f does not allow array value, use modifier %?f[] instead.'],
			['?f[]', [new stdClass()], 'Modifier %?f expects value to be (finite) float, stdClass given.'],

			['b', 'true', 'Modifier %b expects value to be bool, string given.'],
			['b', 1, 'Modifier %b expects value to be bool, integer given.'],
			['b', 1.0, 'Modifier %b expects value to be bool, double given.'],
			['b', [], 'Modifier %b does not allow array value, use modifier %b[] instead.'],
			['b', new stdClass(), 'Modifier %b expects value to be bool, stdClass given.'],
			['b', $file, 'Modifier %b expects value to be bool, SplFileInfo given.'],
			['b', NULL, 'Modifier %b does not allow NULL value, use modifier %?b instead.'],

			['?b', 'true', 'Modifier %?b expects value to be bool, string given.'],
			['?b', 1, 'Modifier %?b expects value to be bool, integer given.'],
			['?b', 1.0, 'Modifier %?b expects value to be bool, double given.'],
			['?b', [], 'Modifier %?b does not allow array value, use modifier %?b[] instead.'],
			['?b', new stdClass(), 'Modifier %?b expects value to be bool, stdClass given.'],
			['?b', $file, 'Modifier %?b expects value to be bool, SplFileInfo given.'],

			['b[]', '123', 'Modifier %b[] expects value to be array, string given.'],
			['b[]', 123, 'Modifier %b[] expects value to be array, integer given.'],
			['b[]', 123.0, 'Modifier %b[] expects value to be array, double given.'],
			['b[]', TRUE, 'Modifier %b[] expects value to be array, boolean given.'],
			['b[]', new stdClass(), 'Modifier %b[] expects value to be array, stdClass given.'],
			['b[]', $file, 'Modifier %b[] expects value to be array, SplFileInfo given.'],
			['b[]', NULL, 'Modifier %b[] expects value to be array, NULL given.'],
			['b[]', ['true'], 'Modifier %b expects value to be bool, string given.'],
			['b[]', [1], 'Modifier %b expects value to be bool, integer given.'],
			['b[]', [1.0], 'Modifier %b expects value to be bool, double given.'],
			['b[]', [[]], 'Modifier %b does not allow array value, use modifier %b[] instead.'],
			['b[]', [new stdClass()], 'Modifier %b expects value to be bool, stdClass given.'],
			['b[]', [NULL], 'Modifier %b does not allow NULL value, use modifier %?b instead.'],

			['?b[]', '123', 'Modifier %?b[] expects value to be array, string given.'],
			['?b[]', 123, 'Modifier %?b[] expects value to be array, integer given.'],
			['?b[]', 123.0, 'Modifier %?b[] expects value to be array, double given.'],
			['?b[]', TRUE, 'Modifier %?b[] expects value to be array, boolean given.'],
			['?b[]', new stdClass(), 'Modifier %?b[] expects value to be array, stdClass given.'],
			['?b[]', $file, 'Modifier %?b[] expects value to be array, SplFileInfo given.'],
			['?b[]', NULL, 'Modifier %?b[] expects value to be array, NULL given.'],
			['?b[]', ['true'], 'Modifier %?b expects value to be bool, string given.'],
			['?b[]', [1], 'Modifier %?b expects value to be bool, integer given.'],
			['?b[]', [1.0], 'Modifier %?b expects value to be bool, double given.'],
			['?b[]', [[]], 'Modifier %?b does not allow array value, use modifier %?b[] instead.'],
			['?b[]', [new stdClass()], 'Modifier %?b expects value to be bool, stdClass given.'],

			['dt', 'true', 'Modifier %dt expects value to be DateTime, string given.'],
			['dt', 1, 'Modifier %dt expects value to be DateTime, integer given.'],
			['dt', 1.0, 'Modifier %dt expects value to be DateTime, double given.'],
			['dt', TRUE, 'Modifier %dt expects value to be DateTime, boolean given.'],
			['dt', [], 'Modifier %dt does not allow array value, use modifier %dt[] instead.'],
			['dt', new stdClass(), 'Modifier %dt expects value to be DateTime, stdClass given.'],
			['dt', $file, 'Modifier %dt expects value to be DateTime, SplFileInfo given.'],
			['dt', NULL, 'Modifier %dt does not allow NULL value, use modifier %?dt instead.'],

			['?dt', 'true', 'Modifier %?dt expects value to be DateTime, string given.'],
			['?dt', 1, 'Modifier %?dt expects value to be DateTime, integer given.'],
			['?dt', 1.0, 'Modifier %?dt expects value to be DateTime, double given.'],
			['?dt', TRUE, 'Modifier %?dt expects value to be DateTime, boolean given.'],
			['?dt', [], 'Modifier %?dt does not allow array value, use modifier %?dt[] instead.'],
			['?dt', new stdClass(), 'Modifier %?dt expects value to be DateTime, stdClass given.'],
			['?dt', $file, 'Modifier %?dt expects value to be DateTime, SplFileInfo given.'],

			['dt[]', '123', 'Modifier %dt[] expects value to be array, string given.'],
			['dt[]', 123, 'Modifier %dt[] expects value to be array, integer given.'],
			['dt[]', 123.0, 'Modifier %dt[] expects value to be array, double given.'],
			['dt[]', TRUE, 'Modifier %dt[] expects value to be array, boolean given.'],
			['dt[]', new stdClass(), 'Modifier %dt[] expects value to be array, stdClass given.'],
			['dt[]', $file, 'Modifier %dt[] expects value to be array, SplFileInfo given.'],
			['dt[]', NULL, 'Modifier %dt[] expects value to be array, NULL given.'],
			['dt[]', ['true'], 'Modifier %dt expects value to be DateTime, string given.'],
			['dt[]', [1], 'Modifier %dt expects value to be DateTime, integer given.'],
			['dt[]', [1.0], 'Modifier %dt expects value to be DateTime, double given.'],
			['dt[]', [TRUE], 'Modifier %dt expects value to be DateTime, boolean given.'],
			['dt[]', [[]], 'Modifier %dt does not allow array value, use modifier %dt[] instead.'],
			['dt[]', [new stdClass()], 'Modifier %dt expects value to be DateTime, stdClass given.'],
			['dt[]', [NULL], 'Modifier %dt does not allow NULL value, use modifier %?dt instead.'],

			['?dt[]', '123', 'Modifier %?dt[] expects value to be array, string given.'],
			['?dt[]', 123, 'Modifier %?dt[] expects value to be array, integer given.'],
			['?dt[]', 123.0, 'Modifier %?dt[] expects value to be array, double given.'],
			['?dt[]', TRUE, 'Modifier %?dt[] expects value to be array, boolean given.'],
			['?dt[]', new stdClass(), 'Modifier %?dt[] expects value to be array, stdClass given.'],
			['?dt[]', $file, 'Modifier %?dt[] expects value to be array, SplFileInfo given.'],
			['?dt[]', NULL, 'Modifier %?dt[] expects value to be array, NULL given.'],
			['?dt[]', ['true'], 'Modifier %?dt expects value to be DateTime, string given.'],
			['?dt[]', [1], 'Modifier %?dt expects value to be DateTime, integer given.'],
			['?dt[]', [1.0], 'Modifier %?dt expects value to be DateTime, double given.'],
			['?dt[]', [TRUE], 'Modifier %?dt expects value to be DateTime, boolean given.'],
			['?dt[]', [[]], 'Modifier %?dt does not allow array value, use modifier %?dt[] instead.'],
			['?dt[]', [new stdClass()], 'Modifier %?dt expects value to be DateTime, stdClass given.'],

			['dts', 'true', 'Modifier %dts expects value to be DateTime, string given.'],
			['dts', 1, 'Modifier %dts expects value to be DateTime, integer given.'],
			['dts', 1.0, 'Modifier %dts expects value to be DateTime, double given.'],
			['dts', TRUE, 'Modifier %dts expects value to be DateTime, boolean given.'],
			['dts', [], 'Modifier %dts does not allow array value, use modifier %dts[] instead.'],
			['dts', new stdClass(), 'Modifier %dts expects value to be DateTime, stdClass given.'],
			['dts', $file, 'Modifier %dts expects value to be DateTime, SplFileInfo given.'],
			['dts', NULL, 'Modifier %dts does not allow NULL value, use modifier %?dts instead.'],

			['?dts', 'true', 'Modifier %?dts expects value to be DateTime, string given.'],
			['?dts', 1, 'Modifier %?dts expects value to be DateTime, integer given.'],
			['?dts', 1.0, 'Modifier %?dts expects value to be DateTime, double given.'],
			['?dts', TRUE, 'Modifier %?dts expects value to be DateTime, boolean given.'],
			['?dts', [], 'Modifier %?dts does not allow array value, use modifier %?dts[] instead.'],
			['?dts', new stdClass(), 'Modifier %?dts expects value to be DateTime, stdClass given.'],
			['?dts', $file, 'Modifier %?dts expects value to be DateTime, SplFileInfo given.'],

			['dts[]', '123', 'Modifier %dts[] expects value to be array, string given.'],
			['dts[]', 123, 'Modifier %dts[] expects value to be array, integer given.'],
			['dts[]', 123.0, 'Modifier %dts[] expects value to be array, double given.'],
			['dts[]', TRUE, 'Modifier %dts[] expects value to be array, boolean given.'],
			['dts[]', new stdClass(), 'Modifier %dts[] expects value to be array, stdClass given.'],
			['dts[]', $file, 'Modifier %dts[] expects value to be array, SplFileInfo given.'],
			['dts[]', NULL, 'Modifier %dts[] expects value to be array, NULL given.'],
			['dts[]', ['true'], 'Modifier %dts expects value to be DateTime, string given.'],
			['dts[]', [1], 'Modifier %dts expects value to be DateTime, integer given.'],
			['dts[]', [1.0], 'Modifier %dts expects value to be DateTime, double given.'],
			['dts[]', [TRUE], 'Modifier %dts expects value to be DateTime, boolean given.'],
			['dts[]', [[]], 'Modifier %dts does not allow array value, use modifier %dts[] instead.'],
			['dts[]', [new stdClass()], 'Modifier %dts expects value to be DateTime, stdClass given.'],
			['dts[]', [NULL], 'Modifier %dts does not allow NULL value, use modifier %?dts instead.'],

			['?dts[]', '123', 'Modifier %?dts[] expects value to be array, string given.'],
			['?dts[]', 123, 'Modifier %?dts[] expects value to be array, integer given.'],
			['?dts[]', 123.0, 'Modifier %?dts[] expects value to be array, double given.'],
			['?dts[]', TRUE, 'Modifier %?dts[] expects value to be array, boolean given.'],
			['?dts[]', new stdClass(), 'Modifier %?dts[] expects value to be array, stdClass given.'],
			['?dts[]', $file, 'Modifier %?dts[] expects value to be array, SplFileInfo given.'],
			['?dts[]', NULL, 'Modifier %?dts[] expects value to be array, NULL given.'],
			['?dts[]', ['true'], 'Modifier %?dts expects value to be DateTime, string given.'],
			['?dts[]', [1], 'Modifier %?dts expects value to be DateTime, integer given.'],
			['?dts[]', [1.0], 'Modifier %?dts expects value to be DateTime, double given.'],
			['?dts[]', [TRUE], 'Modifier %?dts expects value to be DateTime, boolean given.'],
			['?dts[]', [[]], 'Modifier %?dts does not allow array value, use modifier %?dts[] instead.'],
			['?dts[]', [new stdClass()], 'Modifier %?dts expects value to be DateTime, stdClass given.'],

			['di', 'true', 'Modifier %di expects value to be DateInterval, string given.'],
			['di', 1, 'Modifier %di expects value to be DateInterval, integer given.'],
			['di', 1.0, 'Modifier %di expects value to be DateInterval, double given.'],
			['di', TRUE, 'Modifier %di expects value to be DateInterval, boolean given.'],
			['di', [], 'Modifier %di does not allow array value, use modifier %di[] instead.'],
			['di', new stdClass(), 'Modifier %di expects value to be DateInterval, stdClass given.'],
			['di', $file, 'Modifier %di expects value to be DateInterval, SplFileInfo given.'],
			['di', NULL, 'Modifier %di does not allow NULL value, use modifier %?di instead.'],

			['?di', 'true', 'Modifier %?di expects value to be DateInterval, string given.'],
			['?di', 1, 'Modifier %?di expects value to be DateInterval, integer given.'],
			['?di', 1.0, 'Modifier %?di expects value to be DateInterval, double given.'],
			['?di', TRUE, 'Modifier %?di expects value to be DateInterval, boolean given.'],
			['?di', [], 'Modifier %?di does not allow array value, use modifier %?di[] instead.'],
			['?di', new stdClass(), 'Modifier %?di expects value to be DateInterval, stdClass given.'],
			['?di', $file, 'Modifier %?di expects value to be DateInterval, SplFileInfo given.'],

			['di[]', '123', 'Modifier %di[] expects value to be array, string given.'],
			['di[]', 123, 'Modifier %di[] expects value to be array, integer given.'],
			['di[]', 123.0, 'Modifier %di[] expects value to be array, double given.'],
			['di[]', TRUE, 'Modifier %di[] expects value to be array, boolean given.'],
			['di[]', new stdClass(), 'Modifier %di[] expects value to be array, stdClass given.'],
			['di[]', $file, 'Modifier %di[] expects value to be array, SplFileInfo given.'],
			['di[]', NULL, 'Modifier %di[] expects value to be array, NULL given.'],
			['di[]', ['true'], 'Modifier %di expects value to be DateInterval, string given.'],
			['di[]', [1], 'Modifier %di expects value to be DateInterval, integer given.'],
			['di[]', [1.0], 'Modifier %di expects value to be DateInterval, double given.'],
			['di[]', [TRUE], 'Modifier %di expects value to be DateInterval, boolean given.'],
			['di[]', [[]], 'Modifier %di does not allow array value, use modifier %di[] instead.'],
			['di[]', [new stdClass()], 'Modifier %di expects value to be DateInterval, stdClass given.'],
			['di[]', [NULL], 'Modifier %di does not allow NULL value, use modifier %?di instead.'],

			['?di[]', '123', 'Modifier %?di[] expects value to be array, string given.'],
			['?di[]', 123, 'Modifier %?di[] expects value to be array, integer given.'],
			['?di[]', 123.0, 'Modifier %?di[] expects value to be array, double given.'],
			['?di[]', TRUE, 'Modifier %?di[] expects value to be array, boolean given.'],
			['?di[]', new stdClass(), 'Modifier %?di[] expects value to be array, stdClass given.'],
			['?di[]', $file, 'Modifier %?di[] expects value to be array, SplFileInfo given.'],
			['?di[]', NULL, 'Modifier %?di[] expects value to be array, NULL given.'],
			['?di[]', ['true'], 'Modifier %?di expects value to be DateInterval, string given.'],
			['?di[]', [1], 'Modifier %?di expects value to be DateInterval, integer given.'],
			['?di[]', [1.0], 'Modifier %?di expects value to be DateInterval, double given.'],
			['?di[]', [TRUE], 'Modifier %?di expects value to be DateInterval, boolean given.'],
			['?di[]', [[]], 'Modifier %?di does not allow array value, use modifier %?di[] instead.'],
			['?di[]', [new stdClass()], 'Modifier %?di expects value to be DateInterval, stdClass given.'],

			['any', new stdClass(), 'Modifier %any expects value to be pretty much anything, stdClass given.'],
		];
	}

}

$test = new SqlProcessorScalarTest();
$test->run();
