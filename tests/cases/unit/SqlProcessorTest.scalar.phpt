<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

use DateTime;
use Mockery;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\IPlatform;
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
		$this->driver = Mockery::mock(IDriver::class);
		$this->parser = new SqlProcessor($this->driver, \Mockery::mock(IPlatform::class));
	}


	public function testString()
	{
		$this->driver->shouldReceive('convertStringToSql')->once()->with('A')->andReturn('B');
		Assert::same('B', $this->parser->processModifier('s', 'A'));

		// object with __toString
		$file = new \SplFileInfo('C');
		$this->driver->shouldReceive('convertStringToSql')->once()->with('C')->andReturn('D');
		Assert::same('D', $this->parser->processModifier('s', $file));
	}


	public function testJson()
	{
		$this->driver->shouldReceive('convertJsonToSql')->once()->with('A')->andReturn('{}');
		Assert::same('{}', $this->parser->processModifier('json', 'A'));
		$this->driver->shouldReceive('convertJsonToSql')->once()->with(1)->andReturn('{}');
		Assert::same('{}', $this->parser->processModifier('json', 1));
		$this->driver->shouldReceive('convertJsonToSql')->once()->with(1.2)->andReturn('{}');
		Assert::same('{}', $this->parser->processModifier('json', 1.2));
		$this->driver->shouldReceive('convertJsonToSql')->once()->with(true)->andReturn('{}');
		Assert::same('{}', $this->parser->processModifier('json', true));
		$this->driver->shouldReceive('convertJsonToSql')->once()->with([])->andReturn('{}');
		Assert::same('{}', $this->parser->processModifier('json', []));
		$object = (object) [];
		$this->driver->shouldReceive('convertJsonToSql')->once()->with($object)->andReturn('{}');
		Assert::same('{}', $this->parser->processModifier('json', $object));
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
		if (PHP_VERSION_ID >= 70100) {
			Assert::same('1000000000000000.0', $this->parser->processModifier('f', 1e15));
		} else {
			Assert::same('1.0e+15', $this->parser->processModifier('f', 1e15));
		}
	}


	public function testBool()
	{
		$this->driver->shouldReceive('convertBoolToSql')->once()->with(true)->andReturn('T');
		Assert::same('T', $this->parser->processModifier('b', true));

		$this->driver->shouldReceive('convertBoolToSql')->once()->with(false)->andReturn('F');
		Assert::same('F', $this->parser->processModifier('b', false));
	}


	public function testDateTime()
	{
		$dt = new DateTime('2012-03-05 12:01');
		$this->driver->shouldReceive('convertDateTimeToSql')->once()->with($dt)->andReturn('DT');
		Assert::same('DT', $this->parser->processModifier('dt', $dt));
	}


	public function testLocalDateTime()
	{
		$dt = new DateTime('2012-03-05 12:01');
		$this->driver->shouldReceive('convertDateTimeSimpleToSql')->once()->with($dt)->andReturn('LDT');
		Assert::same('LDT', $this->parser->processModifier('ldt', $dt));
	}


	public function testBlob()
	{
		$this->driver->shouldReceive('convertBlobToSql')->once()->with('a10b')->andReturn('B');
		Assert::same('B', $this->parser->processModifier('blob', 'a10b'));
	}


	public function testColumn()
	{
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('foo')->andReturn('FOO');
		Assert::same('FOO', $this->parser->processModifier('column', 'foo'));


		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('a')->andReturn('A');
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('b')->andReturn('B');
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('c')->andReturn('C');
		Assert::same('A, B, C', $this->parser->processModifier('column[]', ['a', 'b', 'c']));


		Assert::exception(function() {
			// test break to process non-string values
			$this->parser->processModifier('column[]', [1]);
		}, InvalidArgumentException::class, 'Modifier %column expects value to be string, integer given.');
	}


	public function testTable()
	{
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('foo')->andReturn('FOO');
		Assert::same('FOO', $this->parser->processModifier('table', 'foo'));


		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('a')->andReturn('A');
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('b')->andReturn('B');
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('c')->andReturn('C');
		Assert::same('A, B, C', $this->parser->processModifier('table[]', ['a', 'b', 'c']));


		Assert::exception(function() {
			// test break to process non-string values
			$this->parser->processModifier('table[]', [1]);
		}, InvalidArgumentException::class, 'Modifier %table expects value to be string, integer given.');
	}


	public function testAny()
	{
		$dt = new DateTime('2012-03-05 12:01');
		$di = $dt->diff(new DateTime('2012-03-05 08:00'));

		$this->driver->shouldReceive('convertStringToSql')->once()->with('A')->andReturn('B');
		$this->driver->shouldReceive('convertBoolToSql')->once()->with(true)->andReturn('T');
		$this->driver->shouldReceive('convertDateTimeToSql')->once()->with($dt)->andReturn('DT');
		Assert::same('B', $this->parser->processModifier('any', 'A'));
		Assert::same('123', $this->parser->processModifier('any', 123));
		Assert::same('123.4', $this->parser->processModifier('any', 123.4));
		Assert::same('T', $this->parser->processModifier('any', true));
		Assert::same('DT', $this->parser->processModifier('any', $dt));
		Assert::same('NULL', $this->parser->processModifier('any', null));

		$this->driver->shouldReceive('convertStringToSql')->once()->with('A')->andReturn('B');
		$this->driver->shouldReceive('convertBoolToSql')->once()->with(true)->andReturn('T');
		$this->driver->shouldReceive('convertDateTimeToSql')->once()->with($dt)->andReturn('DT');
		$this->driver->shouldReceive('convertDateIntervalToSql')->once()->with($di)->andReturn('DI');
		Assert::same('(B, 123, 123.4, T, DT, DI, NULL)', $this->parser->processModifier('any', ['A', 123, 123.4, true, $dt, $di, null]));
	}


	public function testNullable()
	{
		Assert::same('NULL', $this->parser->processModifier('?s', null));
		Assert::same('NULL', $this->parser->processModifier('?i', null));
		Assert::same('NULL', $this->parser->processModifier('?f', null));
		Assert::same('NULL', $this->parser->processModifier('?b', null));
		Assert::same('NULL', $this->parser->processModifier('?dt', null));
		Assert::same('NULL', $this->parser->processModifier('?ldt', null));
		Assert::same('NULL', $this->parser->processModifier('?di', null));
		Assert::same('NULL', $this->parser->processModifier('?json', null));
		Assert::same('NULL', $this->parser->processModifier('any', null));
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
			InvalidArgumentException::class, $message
		);
	}


	public function provideInvalidData()
	{
		// object with __toString
		$file = new \SplFileInfo('C');

		return [
			['s', 123, 'Modifier %s expects value to be string, integer given.'],
			['s', 123.0, 'Modifier %s expects value to be string, double given.'],
			['s', true, 'Modifier %s expects value to be string, boolean given.'],
			['s', [], 'Modifier %s does not allow array value, use modifier %s[] instead.'],
			['s', new stdClass(), 'Modifier %s expects value to be string, stdClass given.'],
			['s', null, 'Modifier %s does not allow NULL value, use modifier %?s instead.'],

			['?s', 123, 'Modifier %?s expects value to be string, integer given.'],
			['?s', 123.0, 'Modifier %?s expects value to be string, double given.'],
			['?s', true, 'Modifier %?s expects value to be string, boolean given.'],
			['?s', [], 'Modifier %?s does not allow array value, use modifier %?s[] instead.'],
			['?s', new stdClass(), 'Modifier %?s expects value to be string, stdClass given.'],

			['s[]', '123', 'Modifier %s[] expects value to be array, string given.'],
			['s[]', 123, 'Modifier %s[] expects value to be array, integer given.'],
			['s[]', 123.0, 'Modifier %s[] expects value to be array, double given.'],
			['s[]', true, 'Modifier %s[] expects value to be array, boolean given.'],
			['s[]', new stdClass(), 'Modifier %s[] expects value to be array, stdClass given.'],
			['s[]', $file, 'Modifier %s[] expects value to be array, SplFileInfo given.'],
			['s[]', null, 'Modifier %s[] expects value to be array, NULL given.'],
			['s[]', [123], 'Modifier %s expects value to be string, integer given.'],
			['s[]', [123.0], 'Modifier %s expects value to be string, double given.'],
			['s[]', [true], 'Modifier %s expects value to be string, boolean given.'],
			['s[]', [[]], 'Modifier %s does not allow array value, use modifier %s[] instead.'],
			['s[]', [new stdClass()], 'Modifier %s expects value to be string, stdClass given.'],
			['s[]', [null], 'Modifier %s does not allow NULL value, use modifier %?s instead.'],

			['?s[]', '123', 'Modifier %?s[] expects value to be array, string given.'],
			['?s[]', 123, 'Modifier %?s[] expects value to be array, integer given.'],
			['?s[]', 123.0, 'Modifier %?s[] expects value to be array, double given.'],
			['?s[]', true, 'Modifier %?s[] expects value to be array, boolean given.'],
			['?s[]', new stdClass(), 'Modifier %?s[] expects value to be array, stdClass given.'],
			['?s[]', $file, 'Modifier %?s[] expects value to be array, SplFileInfo given.'],
			['?s[]', null, 'Modifier %?s[] expects value to be array, NULL given.'],
			['?s[]', [123], 'Modifier %?s expects value to be string, integer given.'],
			['?s[]', [123.0], 'Modifier %?s expects value to be string, double given.'],
			['?s[]', [true], 'Modifier %?s expects value to be string, boolean given.'],
			['?s[]', [[]], 'Modifier %?s does not allow array value, use modifier %?s[] instead.'],
			['?s[]', [new stdClass()], 'Modifier %?s expects value to be string, stdClass given.'],

			['json', null, 'Modifier %json does not allow NULL value, use modifier %?json instead.'],
			['json[]', [null], 'Modifier %json does not allow NULL value, use modifier %?json instead.'],

			['i', '123x', 'Modifier %i expects value to be int, string given.'],
			['i', '0123', 'Modifier %i expects value to be int, string given.'],
			['i', 123.0, 'Modifier %i expects value to be int, double given.'],
			['i', true, 'Modifier %i expects value to be int, boolean given.'],
			['i', [], 'Modifier %i does not allow array value, use modifier %i[] instead.'],
			['i', new stdClass(), 'Modifier %i expects value to be int, stdClass given.'],
			['i', $file, 'Modifier %i expects value to be int, SplFileInfo given.'],
			['i', null, 'Modifier %i does not allow NULL value, use modifier %?i instead.'],

			['?i', '123x', 'Modifier %?i expects value to be int, string given.'],
			['?i', '0123', 'Modifier %?i expects value to be int, string given.'],
			['?i', 123.0, 'Modifier %?i expects value to be int, double given.'],
			['?i', true, 'Modifier %?i expects value to be int, boolean given.'],
			['?i', [], 'Modifier %?i does not allow array value, use modifier %?i[] instead.'],
			['?i', new stdClass(), 'Modifier %?i expects value to be int, stdClass given.'],
			['?i', $file, 'Modifier %?i expects value to be int, SplFileInfo given.'],

			['i[]', '123', 'Modifier %i[] expects value to be array, string given.'],
			['i[]', 123, 'Modifier %i[] expects value to be array, integer given.'],
			['i[]', 123.0, 'Modifier %i[] expects value to be array, double given.'],
			['i[]', true, 'Modifier %i[] expects value to be array, boolean given.'],
			['i[]', new stdClass(), 'Modifier %i[] expects value to be array, stdClass given.'],
			['i[]', $file, 'Modifier %i[] expects value to be array, SplFileInfo given.'],
			['i[]', null, 'Modifier %i[] expects value to be array, NULL given.'],
			['i[]', ['123x'], 'Modifier %i expects value to be int, string given.'],
			['i[]', [123.0], 'Modifier %i expects value to be int, double given.'],
			['i[]', [true], 'Modifier %i expects value to be int, boolean given.'],
			['i[]', [[]], 'Modifier %i does not allow array value, use modifier %i[] instead.'],
			['i[]', [new stdClass()], 'Modifier %i expects value to be int, stdClass given.'],
			['i[]', [null], 'Modifier %i does not allow NULL value, use modifier %?i instead.'],

			['?i[]', '123', 'Modifier %?i[] expects value to be array, string given.'],
			['?i[]', 123, 'Modifier %?i[] expects value to be array, integer given.'],
			['?i[]', 123.0, 'Modifier %?i[] expects value to be array, double given.'],
			['?i[]', true, 'Modifier %?i[] expects value to be array, boolean given.'],
			['?i[]', new stdClass(), 'Modifier %?i[] expects value to be array, stdClass given.'],
			['?i[]', $file, 'Modifier %?i[] expects value to be array, SplFileInfo given.'],
			['?i[]', null, 'Modifier %?i[] expects value to be array, NULL given.'],
			['?i[]', ['123x'], 'Modifier %?i expects value to be int, string given.'],
			['?i[]', ['0123'], 'Modifier %?i expects value to be int, string given.'],
			['?i[]', [123.0], 'Modifier %?i expects value to be int, double given.'],
			['?i[]', [true], 'Modifier %?i expects value to be int, boolean given.'],
			['?i[]', [[]], 'Modifier %?i does not allow array value, use modifier %?i[] instead.'],
			['?i[]', [new stdClass()], 'Modifier %?i expects value to be int, stdClass given.'],

			['f', NAN, 'Modifier %f expects value to be (finite) float, NAN given.'],
			['f', NAN, 'Modifier %f expects value to be (finite) float, NAN given.'],
			['f', +INF, 'Modifier %f expects value to be (finite) float, INF given.'],
			['f', -INF, 'Modifier %f expects value to be (finite) float, -INF given.'],
			['f', '123.4', 'Modifier %f expects value to be (finite) float, string given.'],
			['f', 123, 'Modifier %f expects value to be (finite) float, integer given.'],
			['f', true, 'Modifier %f expects value to be (finite) float, boolean given.'],
			['f', [], 'Modifier %f does not allow array value, use modifier %f[] instead.'],
			['f', new stdClass(), 'Modifier %f expects value to be (finite) float, stdClass given.'],
			['f', $file, 'Modifier %f expects value to be (finite) float, SplFileInfo given.'],
			['f', null, 'Modifier %f does not allow NULL value, use modifier %?f instead.'],

			['?f', NAN, 'Modifier %?f expects value to be (finite) float, NAN given.'],
			['?f', NAN, 'Modifier %?f expects value to be (finite) float, NAN given.'],
			['?f', +INF, 'Modifier %?f expects value to be (finite) float, INF given.'],
			['?f', -INF, 'Modifier %?f expects value to be (finite) float, -INF given.'],
			['?f', '123.4', 'Modifier %?f expects value to be (finite) float, string given.'],
			['?f', 123, 'Modifier %?f expects value to be (finite) float, integer given.'],
			['?f', true, 'Modifier %?f expects value to be (finite) float, boolean given.'],
			['?f', [], 'Modifier %?f does not allow array value, use modifier %?f[] instead.'],
			['?f', new stdClass(), 'Modifier %?f expects value to be (finite) float, stdClass given.'],
			['?f', $file, 'Modifier %?f expects value to be (finite) float, SplFileInfo given.'],

			['f[]', '123', 'Modifier %f[] expects value to be array, string given.'],
			['f[]', 123, 'Modifier %f[] expects value to be array, integer given.'],
			['f[]', 123.0, 'Modifier %f[] expects value to be array, double given.'],
			['f[]', true, 'Modifier %f[] expects value to be array, boolean given.'],
			['f[]', new stdClass(), 'Modifier %f[] expects value to be array, stdClass given.'],
			['f[]', $file, 'Modifier %f[] expects value to be array, SplFileInfo given.'],
			['f[]', null, 'Modifier %f[] expects value to be array, NULL given.'],
			['f[]', [NAN], 'Modifier %f expects value to be (finite) float, NAN given.'],
			['f[]', [NAN], 'Modifier %f expects value to be (finite) float, NAN given.'],
			['f[]', [+INF], 'Modifier %f expects value to be (finite) float, INF given.'],
			['f[]', [-INF], 'Modifier %f expects value to be (finite) float, -INF given.'],
			['f[]', ['123.4'], 'Modifier %f expects value to be (finite) float, string given.'],
			['f[]', [123], 'Modifier %f expects value to be (finite) float, integer given.'],
			['f[]', [true], 'Modifier %f expects value to be (finite) float, boolean given.'],
			['f[]', [[]], 'Modifier %f does not allow array value, use modifier %f[] instead.'],
			['f[]', [new stdClass()], 'Modifier %f expects value to be (finite) float, stdClass given.'],
			['f[]', [null], 'Modifier %f does not allow NULL value, use modifier %?f instead.'],

			['?f[]', '123', 'Modifier %?f[] expects value to be array, string given.'],
			['?f[]', 123, 'Modifier %?f[] expects value to be array, integer given.'],
			['?f[]', 123.0, 'Modifier %?f[] expects value to be array, double given.'],
			['?f[]', true, 'Modifier %?f[] expects value to be array, boolean given.'],
			['?f[]', new stdClass(), 'Modifier %?f[] expects value to be array, stdClass given.'],
			['?f[]', $file, 'Modifier %?f[] expects value to be array, SplFileInfo given.'],
			['?f[]', null, 'Modifier %?f[] expects value to be array, NULL given.'],
			['?f[]', [NAN], 'Modifier %?f expects value to be (finite) float, NAN given.'],
			['?f[]', [NAN], 'Modifier %?f expects value to be (finite) float, NAN given.'],
			['?f[]', [+INF], 'Modifier %?f expects value to be (finite) float, INF given.'],
			['?f[]', [-INF], 'Modifier %?f expects value to be (finite) float, -INF given.'],
			['?f[]', ['123.4'], 'Modifier %?f expects value to be (finite) float, string given.'],
			['?f[]', [123], 'Modifier %?f expects value to be (finite) float, integer given.'],
			['?f[]', [true], 'Modifier %?f expects value to be (finite) float, boolean given.'],
			['?f[]', [[]], 'Modifier %?f does not allow array value, use modifier %?f[] instead.'],
			['?f[]', [new stdClass()], 'Modifier %?f expects value to be (finite) float, stdClass given.'],

			['b', 'true', 'Modifier %b expects value to be bool, string given.'],
			['b', 1, 'Modifier %b expects value to be bool, integer given.'],
			['b', 1.0, 'Modifier %b expects value to be bool, double given.'],
			['b', [], 'Modifier %b does not allow array value, use modifier %b[] instead.'],
			['b', new stdClass(), 'Modifier %b expects value to be bool, stdClass given.'],
			['b', $file, 'Modifier %b expects value to be bool, SplFileInfo given.'],
			['b', null, 'Modifier %b does not allow NULL value, use modifier %?b instead.'],

			['?b', 'true', 'Modifier %?b expects value to be bool, string given.'],
			['?b', 1, 'Modifier %?b expects value to be bool, integer given.'],
			['?b', 1.0, 'Modifier %?b expects value to be bool, double given.'],
			['?b', [], 'Modifier %?b does not allow array value, use modifier %?b[] instead.'],
			['?b', new stdClass(), 'Modifier %?b expects value to be bool, stdClass given.'],
			['?b', $file, 'Modifier %?b expects value to be bool, SplFileInfo given.'],

			['b[]', '123', 'Modifier %b[] expects value to be array, string given.'],
			['b[]', 123, 'Modifier %b[] expects value to be array, integer given.'],
			['b[]', 123.0, 'Modifier %b[] expects value to be array, double given.'],
			['b[]', true, 'Modifier %b[] expects value to be array, boolean given.'],
			['b[]', new stdClass(), 'Modifier %b[] expects value to be array, stdClass given.'],
			['b[]', $file, 'Modifier %b[] expects value to be array, SplFileInfo given.'],
			['b[]', null, 'Modifier %b[] expects value to be array, NULL given.'],
			['b[]', ['true'], 'Modifier %b expects value to be bool, string given.'],
			['b[]', [1], 'Modifier %b expects value to be bool, integer given.'],
			['b[]', [1.0], 'Modifier %b expects value to be bool, double given.'],
			['b[]', [[]], 'Modifier %b does not allow array value, use modifier %b[] instead.'],
			['b[]', [new stdClass()], 'Modifier %b expects value to be bool, stdClass given.'],
			['b[]', [null], 'Modifier %b does not allow NULL value, use modifier %?b instead.'],

			['?b[]', '123', 'Modifier %?b[] expects value to be array, string given.'],
			['?b[]', 123, 'Modifier %?b[] expects value to be array, integer given.'],
			['?b[]', 123.0, 'Modifier %?b[] expects value to be array, double given.'],
			['?b[]', true, 'Modifier %?b[] expects value to be array, boolean given.'],
			['?b[]', new stdClass(), 'Modifier %?b[] expects value to be array, stdClass given.'],
			['?b[]', $file, 'Modifier %?b[] expects value to be array, SplFileInfo given.'],
			['?b[]', null, 'Modifier %?b[] expects value to be array, NULL given.'],
			['?b[]', ['true'], 'Modifier %?b expects value to be bool, string given.'],
			['?b[]', [1], 'Modifier %?b expects value to be bool, integer given.'],
			['?b[]', [1.0], 'Modifier %?b expects value to be bool, double given.'],
			['?b[]', [[]], 'Modifier %?b does not allow array value, use modifier %?b[] instead.'],
			['?b[]', [new stdClass()], 'Modifier %?b expects value to be bool, stdClass given.'],

			['dt', 'true', 'Modifier %dt expects value to be DateTime, string given.'],
			['dt', 1, 'Modifier %dt expects value to be DateTime, integer given.'],
			['dt', 1.0, 'Modifier %dt expects value to be DateTime, double given.'],
			['dt', true, 'Modifier %dt expects value to be DateTime, boolean given.'],
			['dt', [], 'Modifier %dt does not allow array value, use modifier %dt[] instead.'],
			['dt', new stdClass(), 'Modifier %dt expects value to be DateTime, stdClass given.'],
			['dt', $file, 'Modifier %dt expects value to be DateTime, SplFileInfo given.'],
			['dt', null, 'Modifier %dt does not allow NULL value, use modifier %?dt instead.'],

			['?dt', 'true', 'Modifier %?dt expects value to be DateTime, string given.'],
			['?dt', 1, 'Modifier %?dt expects value to be DateTime, integer given.'],
			['?dt', 1.0, 'Modifier %?dt expects value to be DateTime, double given.'],
			['?dt', true, 'Modifier %?dt expects value to be DateTime, boolean given.'],
			['?dt', [], 'Modifier %?dt does not allow array value, use modifier %?dt[] instead.'],
			['?dt', new stdClass(), 'Modifier %?dt expects value to be DateTime, stdClass given.'],
			['?dt', $file, 'Modifier %?dt expects value to be DateTime, SplFileInfo given.'],

			['dt[]', '123', 'Modifier %dt[] expects value to be array, string given.'],
			['dt[]', 123, 'Modifier %dt[] expects value to be array, integer given.'],
			['dt[]', 123.0, 'Modifier %dt[] expects value to be array, double given.'],
			['dt[]', true, 'Modifier %dt[] expects value to be array, boolean given.'],
			['dt[]', new stdClass(), 'Modifier %dt[] expects value to be array, stdClass given.'],
			['dt[]', $file, 'Modifier %dt[] expects value to be array, SplFileInfo given.'],
			['dt[]', null, 'Modifier %dt[] expects value to be array, NULL given.'],
			['dt[]', ['true'], 'Modifier %dt expects value to be DateTime, string given.'],
			['dt[]', [1], 'Modifier %dt expects value to be DateTime, integer given.'],
			['dt[]', [1.0], 'Modifier %dt expects value to be DateTime, double given.'],
			['dt[]', [true], 'Modifier %dt expects value to be DateTime, boolean given.'],
			['dt[]', [[]], 'Modifier %dt does not allow array value, use modifier %dt[] instead.'],
			['dt[]', [new stdClass()], 'Modifier %dt expects value to be DateTime, stdClass given.'],
			['dt[]', [null], 'Modifier %dt does not allow NULL value, use modifier %?dt instead.'],

			['?dt[]', '123', 'Modifier %?dt[] expects value to be array, string given.'],
			['?dt[]', 123, 'Modifier %?dt[] expects value to be array, integer given.'],
			['?dt[]', 123.0, 'Modifier %?dt[] expects value to be array, double given.'],
			['?dt[]', true, 'Modifier %?dt[] expects value to be array, boolean given.'],
			['?dt[]', new stdClass(), 'Modifier %?dt[] expects value to be array, stdClass given.'],
			['?dt[]', $file, 'Modifier %?dt[] expects value to be array, SplFileInfo given.'],
			['?dt[]', null, 'Modifier %?dt[] expects value to be array, NULL given.'],
			['?dt[]', ['true'], 'Modifier %?dt expects value to be DateTime, string given.'],
			['?dt[]', [1], 'Modifier %?dt expects value to be DateTime, integer given.'],
			['?dt[]', [1.0], 'Modifier %?dt expects value to be DateTime, double given.'],
			['?dt[]', [true], 'Modifier %?dt expects value to be DateTime, boolean given.'],
			['?dt[]', [[]], 'Modifier %?dt does not allow array value, use modifier %?dt[] instead.'],
			['?dt[]', [new stdClass()], 'Modifier %?dt expects value to be DateTime, stdClass given.'],

			['ldt', 'true', 'Modifier %ldt expects value to be DateTime, string given.'],
			['ldt', 1, 'Modifier %ldt expects value to be DateTime, integer given.'],
			['ldt', 1.0, 'Modifier %ldt expects value to be DateTime, double given.'],
			['ldt', true, 'Modifier %ldt expects value to be DateTime, boolean given.'],
			['ldt', [], 'Modifier %ldt does not allow array value, use modifier %ldt[] instead.'],
			['ldt', new stdClass(), 'Modifier %ldt expects value to be DateTime, stdClass given.'],
			['ldt', $file, 'Modifier %ldt expects value to be DateTime, SplFileInfo given.'],
			['ldt', null, 'Modifier %ldt does not allow NULL value, use modifier %?ldt instead.'],

			['?ldt', 'true', 'Modifier %?ldt expects value to be DateTime, string given.'],
			['?ldt', 1, 'Modifier %?ldt expects value to be DateTime, integer given.'],
			['?ldt', 1.0, 'Modifier %?ldt expects value to be DateTime, double given.'],
			['?ldt', true, 'Modifier %?ldt expects value to be DateTime, boolean given.'],
			['?ldt', [], 'Modifier %?ldt does not allow array value, use modifier %?ldt[] instead.'],
			['?ldt', new stdClass(), 'Modifier %?ldt expects value to be DateTime, stdClass given.'],
			['?ldt', $file, 'Modifier %?ldt expects value to be DateTime, SplFileInfo given.'],

			['ldt[]', '123', 'Modifier %ldt[] expects value to be array, string given.'],
			['ldt[]', 123, 'Modifier %ldt[] expects value to be array, integer given.'],
			['ldt[]', 123.0, 'Modifier %ldt[] expects value to be array, double given.'],
			['ldt[]', true, 'Modifier %ldt[] expects value to be array, boolean given.'],
			['ldt[]', new stdClass(), 'Modifier %ldt[] expects value to be array, stdClass given.'],
			['ldt[]', $file, 'Modifier %ldt[] expects value to be array, SplFileInfo given.'],
			['ldt[]', null, 'Modifier %ldt[] expects value to be array, NULL given.'],
			['ldt[]', ['true'], 'Modifier %ldt expects value to be DateTime, string given.'],
			['ldt[]', [1], 'Modifier %ldt expects value to be DateTime, integer given.'],
			['ldt[]', [1.0], 'Modifier %ldt expects value to be DateTime, double given.'],
			['ldt[]', [true], 'Modifier %ldt expects value to be DateTime, boolean given.'],
			['ldt[]', [[]], 'Modifier %ldt does not allow array value, use modifier %ldt[] instead.'],
			['ldt[]', [new stdClass()], 'Modifier %ldt expects value to be DateTime, stdClass given.'],
			['ldt[]', [null], 'Modifier %ldt does not allow NULL value, use modifier %?ldt instead.'],

			['?ldt[]', '123', 'Modifier %?ldt[] expects value to be array, string given.'],
			['?ldt[]', 123, 'Modifier %?ldt[] expects value to be array, integer given.'],
			['?ldt[]', 123.0, 'Modifier %?ldt[] expects value to be array, double given.'],
			['?ldt[]', true, 'Modifier %?ldt[] expects value to be array, boolean given.'],
			['?ldt[]', new stdClass(), 'Modifier %?ldt[] expects value to be array, stdClass given.'],
			['?ldt[]', $file, 'Modifier %?ldt[] expects value to be array, SplFileInfo given.'],
			['?ldt[]', null, 'Modifier %?ldt[] expects value to be array, NULL given.'],
			['?ldt[]', ['true'], 'Modifier %?ldt expects value to be DateTime, string given.'],
			['?ldt[]', [1], 'Modifier %?ldt expects value to be DateTime, integer given.'],
			['?ldt[]', [1.0], 'Modifier %?ldt expects value to be DateTime, double given.'],
			['?ldt[]', [true], 'Modifier %?ldt expects value to be DateTime, boolean given.'],
			['?ldt[]', [[]], 'Modifier %?ldt does not allow array value, use modifier %?ldt[] instead.'],
			['?ldt[]', [new stdClass()], 'Modifier %?ldt expects value to be DateTime, stdClass given.'],

			['di', 'true', 'Modifier %di expects value to be DateInterval, string given.'],
			['di', 1, 'Modifier %di expects value to be DateInterval, integer given.'],
			['di', 1.0, 'Modifier %di expects value to be DateInterval, double given.'],
			['di', true, 'Modifier %di expects value to be DateInterval, boolean given.'],
			['di', [], 'Modifier %di does not allow array value, use modifier %di[] instead.'],
			['di', new stdClass(), 'Modifier %di expects value to be DateInterval, stdClass given.'],
			['di', $file, 'Modifier %di expects value to be DateInterval, SplFileInfo given.'],
			['di', null, 'Modifier %di does not allow NULL value, use modifier %?di instead.'],

			['?di', 'true', 'Modifier %?di expects value to be DateInterval, string given.'],
			['?di', 1, 'Modifier %?di expects value to be DateInterval, integer given.'],
			['?di', 1.0, 'Modifier %?di expects value to be DateInterval, double given.'],
			['?di', true, 'Modifier %?di expects value to be DateInterval, boolean given.'],
			['?di', [], 'Modifier %?di does not allow array value, use modifier %?di[] instead.'],
			['?di', new stdClass(), 'Modifier %?di expects value to be DateInterval, stdClass given.'],
			['?di', $file, 'Modifier %?di expects value to be DateInterval, SplFileInfo given.'],

			['di[]', '123', 'Modifier %di[] expects value to be array, string given.'],
			['di[]', 123, 'Modifier %di[] expects value to be array, integer given.'],
			['di[]', 123.0, 'Modifier %di[] expects value to be array, double given.'],
			['di[]', true, 'Modifier %di[] expects value to be array, boolean given.'],
			['di[]', new stdClass(), 'Modifier %di[] expects value to be array, stdClass given.'],
			['di[]', $file, 'Modifier %di[] expects value to be array, SplFileInfo given.'],
			['di[]', null, 'Modifier %di[] expects value to be array, NULL given.'],
			['di[]', ['true'], 'Modifier %di expects value to be DateInterval, string given.'],
			['di[]', [1], 'Modifier %di expects value to be DateInterval, integer given.'],
			['di[]', [1.0], 'Modifier %di expects value to be DateInterval, double given.'],
			['di[]', [true], 'Modifier %di expects value to be DateInterval, boolean given.'],
			['di[]', [[]], 'Modifier %di does not allow array value, use modifier %di[] instead.'],
			['di[]', [new stdClass()], 'Modifier %di expects value to be DateInterval, stdClass given.'],
			['di[]', [null], 'Modifier %di does not allow NULL value, use modifier %?di instead.'],

			['?di[]', '123', 'Modifier %?di[] expects value to be array, string given.'],
			['?di[]', 123, 'Modifier %?di[] expects value to be array, integer given.'],
			['?di[]', 123.0, 'Modifier %?di[] expects value to be array, double given.'],
			['?di[]', true, 'Modifier %?di[] expects value to be array, boolean given.'],
			['?di[]', new stdClass(), 'Modifier %?di[] expects value to be array, stdClass given.'],
			['?di[]', $file, 'Modifier %?di[] expects value to be array, SplFileInfo given.'],
			['?di[]', null, 'Modifier %?di[] expects value to be array, NULL given.'],
			['?di[]', ['true'], 'Modifier %?di expects value to be DateInterval, string given.'],
			['?di[]', [1], 'Modifier %?di expects value to be DateInterval, integer given.'],
			['?di[]', [1.0], 'Modifier %?di expects value to be DateInterval, double given.'],
			['?di[]', [true], 'Modifier %?di expects value to be DateInterval, boolean given.'],
			['?di[]', [[]], 'Modifier %?di does not allow array value, use modifier %?di[] instead.'],
			['?di[]', [new stdClass()], 'Modifier %?di expects value to be DateInterval, stdClass given.'],

			['blob', 123, 'Modifier %blob expects value to be blob string, integer given.'],
			['blob', 123.0, 'Modifier %blob expects value to be blob string, double given.'],
			['blob', true, 'Modifier %blob expects value to be blob string, boolean given.'],
			['blob', [], 'Modifier %blob does not allow array value, use modifier %blob[] instead.'],
			['blob', new stdClass(), 'Modifier %blob expects value to be blob string, stdClass given.'],
			['blob', null, 'Modifier %blob does not allow NULL value, use modifier %?blob instead.'],

			['?blob', 123, 'Modifier %?blob expects value to be blob string, integer given.'],
			['?blob', 123.0, 'Modifier %?blob expects value to be blob string, double given.'],
			['?blob', true, 'Modifier %?blob expects value to be blob string, boolean given.'],
			['?blob', [], 'Modifier %?blob does not allow array value, use modifier %?blob[] instead.'],
			['?blob', new stdClass(), 'Modifier %?blob expects value to be blob string, stdClass given.'],

			['blob[]', '123', 'Modifier %blob[] expects value to be array, string given.'],
			['blob[]', 123, 'Modifier %blob[] expects value to be array, integer given.'],
			['blob[]', 123.0, 'Modifier %blob[] expects value to be array, double given.'],
			['blob[]', true, 'Modifier %blob[] expects value to be array, boolean given.'],
			['blob[]', new stdClass(), 'Modifier %blob[] expects value to be array, stdClass given.'],
			['blob[]', $file, 'Modifier %blob[] expects value to be array, SplFileInfo given.'],
			['blob[]', null, 'Modifier %blob[] expects value to be array, NULL given.'],
			['blob[]', [123.0], 'Modifier %blob expects value to be blob string, double given.'],
			['blob[]', [true], 'Modifier %blob expects value to be blob string, boolean given.'],
			['blob[]', [[]], 'Modifier %blob does not allow array value, use modifier %blob[] instead.'],
			['blob[]', [new stdClass()], 'Modifier %blob expects value to be blob string, stdClass given.'],
			['blob[]', [null], 'Modifier %blob does not allow NULL value, use modifier %?blob instead.'],

			['any', new stdClass(), 'Modifier %any expects value to be pretty much anything, stdClass given.'],
		];
	}
}


$test = new SqlProcessorScalarTest();
$test->run();
