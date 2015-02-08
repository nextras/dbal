<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\SqlProcessor;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorMultipleTest extends TestCase
{
	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$driver = \Mockery::mock('Nextras\Dbal\Drivers\IDriver');
		$this->parser = new SqlProcessor($driver);
	}


	public function testMultiple()
	{
		Assert::same(
			'SELECT 1, 2, 3',
			$this->convert('SELECT %i, %i, %i', 1, 2, 3)
		);

		Assert::same(
			'SELECT 1, 2 , 3, 4',
			$this->convert('SELECT %i, %i', 1, 2, ', %i, %i', 3, 4)
		);

		Assert::same(
			'SELECT 1, 2 , 3, 4 WHERE 1=1',
			$this->convert('SELECT %i, %i', 1, 2, ', %i, %i', 3, 4, 'WHERE 1=1')
		);

		Assert::same(
			'SELECT 1 2',
			$this->convert('SELECT %i', 1, '2')
		);
	}


	public function testWrongArguments()
	{
		Assert::throws(function() {
			$this->convert(123);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Query fragment must be string.');

		Assert::throws(function() {
			$this->convert(new \stdClass());
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Query fragment must be string.');

		Assert::throws(function() {
			$this->convert('SELECT %i');
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Missing query parameter for modifier %i.');

		Assert::throws(function() {
			$this->convert('SELECT %i', 1, 1);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \'SELECT %i\'.');

		Assert::throws(function() {
			$this->convert('SELECT %i', 1, 1);
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \'SELECT %i\'.');

		Assert::throws(function() {
			$this->convert('SELECT %i', 1, ' WHERE ', '1=1');
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', 'Redundant query parameter or missing modifier in query fragment \' WHERE \'.');
	}


	private function convert($sql)
	{
		return $this->parser->process(func_get_args());
	}

}

$test = new SqlProcessorMultipleTest();
$test->run();
