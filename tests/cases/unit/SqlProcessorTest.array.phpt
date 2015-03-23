<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorArrayTest extends TestCase
{
	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$driver = \Mockery::mock('Nextras\Dbal\Drivers\IDriver');
		$this->parser = new SqlProcessor($driver);
	}


	public function testArray()
	{
		Assert::same(
			'SELECT FROM test WHERE id IN (1, 2, 3)',
			$this->convert('SELECT FROM test WHERE id IN %i[]', [1, 2, 3])
		);

		Assert::same(
			'SELECT FROM test WHERE id IN ()',
			$this->convert('SELECT FROM test WHERE id IN %i[]', [])
		);

		Assert::same(
			'SELECT FROM test WHERE id IN (NULL, 2, 3)',
			$this->convert('SELECT FROM test WHERE id IN %?i[]', [NULL, 2, 3])
		);
	}


	public function testWhereTuplets()
	{
		Assert::same(
			"SELECT 1 FROM foo WHERE (a, b) IN ((1, 2), (3, 4), (5, 6))",
			$this->convert('SELECT 1 FROM foo WHERE (a, b) IN %i[][]', [
				[1, 2],
				[3, 4],
				[5, 6],
			])
		);
	}


	private function convert($sql)
	{
		return $this->parser->process(func_get_args());
	}

}

$test = new SqlProcessorArrayTest();
$test->run();
