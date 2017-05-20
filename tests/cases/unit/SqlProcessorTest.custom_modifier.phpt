<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorCustomModifierTest extends TestCase
{
	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$driver = \Mockery::mock(IDriver::class);
		$this->parser = new SqlProcessor($driver);
	}


	public function testArray()
	{
		$this->parser->setCustomModifier('yesBool', function ($val) {
			return $val ? "'yes'" : "'no'";
		});

		Assert::same(
			"SELECT FROM test WHERE published = 'yes'",
			$this->convert('SELECT FROM test WHERE published = %yesBool', true)
		);
		Assert::same(
			"SELECT FROM test WHERE published = 'no'",
			$this->convert('SELECT FROM test WHERE published = %yesBool', false)
		);
	}


	public function testWhereTuplets()
	{
		Assert::exception(function() {
			$this->parser->setCustomModifier('s', function () {});
		}, InvalidArgumentException::class);
		Assert::exception(function() {
			$this->parser->setCustomModifier('s?', function () {});
		}, InvalidArgumentException::class);
		Assert::exception(function() {
			$this->parser->setCustomModifier('s[]', function () {});
		}, InvalidArgumentException::class);

		$this->parser->setCustomModifier('yesBool', function () {});

		Assert::exception(function() {
			$this->convert('SELECT FROM test WHERE published IN %yesBool[]', [false]);
		}, InvalidArgumentException::class);
	}


	private function convert($sql)
	{
		return $this->parser->process(func_get_args());
	}
}


$test = new SqlProcessorCustomModifierTest();
$test->run();
