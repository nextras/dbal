<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;


use Mockery;
use Mockery\MockInterface;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorExpandTest extends TestCase
{
	/** @var IPlatform|MockInterface */
	private $platform;

	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->platform = Mockery::mock(IPlatform::class);
		$this->parser = new SqlProcessor($this->platform);
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

		$this->platform->shouldReceive('formatIdentifier')->once()->with('A')->andReturn('B');
		Assert::same(
			'= B + 123',
			$this->parser->processModifier('ex', ['= [A] + 123'])
		);
	}


	public function testInvalid()
	{
		Assert::throws(function () {
			$this->parser->processModifier('ex', 'abc');
		}, InvalidArgumentException::class, 'Modifier %ex expects value to be array, string given.');

		Assert::throws(function () {
			$this->parser->processModifier('?ex', 'abc');
		}, InvalidArgumentException::class, 'Modifier %ex does not have %?ex variant.');

		Assert::throws(function () {
			$this->parser->processModifier('ex[]', 'abc');
		}, InvalidArgumentException::class, 'Modifier %ex does not have %ex[] variant.');

		Assert::throws(function () {
			$this->parser->processModifier('?ex[]', 'abc');
		}, InvalidArgumentException::class, 'Modifier %ex does not have %?ex[] variant.');
	}
}


$test = new SqlProcessorExpandTest();
$test->run();
