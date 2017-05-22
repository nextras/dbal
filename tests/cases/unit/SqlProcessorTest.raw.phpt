<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorRawTest extends TestCase
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


	public function testRaw()
	{
		Assert::same(
			'',
			$this->parser->processModifier('raw', '')
		);

		Assert::same(
			'SELECT [column] %modifier /* comment */',
			$this->parser->processModifier('raw', 'SELECT [column] %modifier /* comment */')
		);
	}


	public function testInvalid()
	{
		Assert::throws(function () {
			$this->parser->processModifier('raw', 123);
		}, InvalidArgumentException::class, 'Modifier %raw expects value to be string, integer given.');

		Assert::throws(function () {
			$this->parser->processModifier('?raw', null);
		}, InvalidArgumentException::class, 'Modifier %raw does not have %?raw variant.');

		Assert::throws(function () {
			$this->parser->processModifier('raw[]', []);
		}, InvalidArgumentException::class, 'Modifier %raw does not have %raw[] variant.');

		Assert::throws(function () {
			$this->parser->processModifier('?raw[]', []);
		}, InvalidArgumentException::class, 'Modifier %raw does not have %?raw[] variant.');
	}
}


$test = new SqlProcessorRawTest();
$test->run();
