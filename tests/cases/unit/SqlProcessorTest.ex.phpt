<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery;
use Mockery\MockInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorExpandTest extends TestCase
{
	/** @var IDriver|MockInterface */
	private $driver;

	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');
		$this->parser = new SqlProcessor($this->driver);
	}


	public function testExpand()
	{
		Assert::same(
			'',
			$this->parser->processModifier('ex', [])
		);

		Assert::same(
			'(1 + 2) * 3',
			$this->parser->processModifier('ex', ['(%i + %i)', 1, 2, '* %i', 3])
		);

		Assert::same(
			'(1 + 2) * 3',
			$this->parser->processModifier('ex', ['%ex', ['%ex', ['%ex', ['(%i + %i)', 1, 2, '* %i', 3]]]])
		);

		Assert::same(
			'IS NULL',
			$this->parser->processModifier('ex', ['IS NULL'])
		);

		$this->driver->shouldReceive('convertToSql')->once()->with('A', IDriver::TYPE_IDENTIFIER)->andReturn('B');
		Assert::same(
			'= B + 123',
			$this->parser->processModifier('ex', ['= [A] + 123'])
		);
	}


	public function testInvalid()
	{
		Assert::throws(function () {
			$this->parser->processModifier('ex', 'abc');
		}, 'Nextras\Dbal\InvalidArgumentException', 'Modifier %ex expects value to be array, string given.');

		Assert::throws(function () {
			$this->parser->processModifier('?ex', 'abc');
		}, 'Nextras\Dbal\InvalidArgumentException', 'Modifier %ex does not have %?ex variant.');

		Assert::throws(function () {
			$this->parser->processModifier('ex[]', 'abc');
		}, 'Nextras\Dbal\InvalidArgumentException', 'Modifier %ex does not have %ex[] variant.');

		Assert::throws(function () {
			$this->parser->processModifier('?ex[]', 'abc');
		}, 'Nextras\Dbal\InvalidArgumentException', 'Modifier %ex does not have %?ex[] variant.');
	}

}

$test = new SqlProcessorExpandTest();
$test->run();
