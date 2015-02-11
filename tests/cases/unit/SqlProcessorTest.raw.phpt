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

}

$test = new SqlProcessorRawTest();
$test->run();
