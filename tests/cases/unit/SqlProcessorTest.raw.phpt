<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery\MockInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorRawTest extends TestCase
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


	public function testRawModifier()
	{
		Assert::same(
			'SELECT a FROM foo',
			$this->parser->process(['SELECT a %raw', 'FROM foo'])
		);
	}


	public function testInvalid()
	{
		Assert::throws(function () {
			$this->parser->processModifier('raw', 123);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Modifier %raw expects value to be string, integer given.');

		Assert::throws(function () {
			$this->parser->processModifier('raw?', NULL);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Modifier %raw does not have %raw? variant.');

		Assert::throws(function () {
			$this->parser->processModifier('raw[]', []);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Modifier %raw does not have %raw[] variant.');

		Assert::throws(function () {
			$this->parser->processModifier('raw?[]', []);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Modifier %raw does not have %raw?[] variant.');
	}

}

$test = new SqlProcessorRawTest();
$test->run();
