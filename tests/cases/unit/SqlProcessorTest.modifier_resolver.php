<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;


use Nextras\Dbal\ISqlProcessorModifierResolver;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorModifierResolverTest extends TestCase
{
	public function testExplicitAny()
	{
		$parser = new SqlProcessor(\Mockery::mock(IPlatform::class));
		$parser->setCustomModifier('nextrasBoolean', function (SqlProcessor $p, $value) {
			if ($value === true) {
				return $p->processModifier('i', 11);
			} else {
				return $p->processModifier('i', -11);
			}
		});
		$parser->addModifierResolver(new class implements ISqlProcessorModifierResolver {
			public function resolve($value): ?string
			{
				if (is_bool($value)) return 'nextrasBoolean'; else return null;
			}
		});

		Assert::same(
			"SELECT FROM test WHERE published = 11",
			$parser->process(['SELECT FROM test WHERE published = %any', true])
		);
		Assert::same(
			"SELECT FROM test WHERE published = -11",
			$parser->process(['SELECT FROM test WHERE published = %any', false])
		);
	}


	public function testImplicitAny()
	{
		$platform = \Mockery::mock(IPlatform::class);
		$platform->shouldReceive('formatIdentifier')->with('published')->andReturn('published');
		$parser = new SqlProcessor($platform);

		$parser->setCustomModifier('nextrasBoolean', function (SqlProcessor $p, $value) {
			if ($value === true) {
				return $p->processModifier('i', 22);
			} else {
				return $p->processModifier('i', -22);
			}
		});
		$parser->addModifierResolver(new class implements ISqlProcessorModifierResolver {
			public function resolve($value): ?string
			{
				if (is_bool($value)) return 'nextrasBoolean'; else return null;
			}
		});

		Assert::same(
			"SELECT FROM test WHERE published = 22",
			$parser->process(['SELECT FROM test WHERE %and', ['published' => true]])
		);
		Assert::same(
			"SELECT FROM test WHERE published = -22",
			$parser->process(['SELECT FROM test WHERE %and', ['published' => false]])
		);
	}
}


$test = new SqlProcessorModifierResolverTest();
$test->run();
