<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\IPlatform;
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
		$this->parser = new SqlProcessor(\Mockery::mock(IPlatform::class));
	}


	public function testArray()
	{
		$this->parser->setCustomModifier('yesBool', function (SqlProcessor $sqlProcessor, $val) {
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


	public function testModifierOverride()
	{
		Assert::exception(function () {
			$this->parser->setCustomModifier(
				's',
				function () {
				}
			);
		}, InvalidArgumentException::class);
		Assert::exception(function () {
			$this->parser->setCustomModifier(
				's?',
				function () {
				}
			);
		}, InvalidArgumentException::class);
		Assert::exception(function () {
			$this->parser->setCustomModifier(
				's[]',
				function () {
				}
			);
		}, InvalidArgumentException::class);
	}


	private function convert($sql)
	{
		return $this->parser->process(func_get_args());
	}
}


$test = new SqlProcessorCustomModifierTest();
$test->run();
