<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

use DateTime;
use Mockery;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\SqlProcessor;
use stdClass;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorWhereTest extends TestCase
{
	/** @var IPlatform|Mockery\MockInterface */
	private $platform;

	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->platform = Mockery::mock(IPlatform::class);
		$this->parser = new SqlProcessor($this->platform);
	}


	/**
	 * @dataProvider provideImplicitTypesData
	 * @dataProvider provideExplicitTypesData
	 */
	public function testImplicitAndExplicitTypes($expected, $operands)
	{
		$this->platform->shouldReceive('formatString')->with('x')->andReturn('"x"');
		$this->platform->shouldReceive('formatIdentifier')->with('col')->andReturn('`col`');
		$this->platform->shouldReceive('formatDateTime')->with(Mockery::type('DateTime'))->andReturn('DT');

		Assert::same($expected, $this->parser->processModifier('and', $operands));
	}


	public function provideImplicitTypesData()
	{
		return [
			[
				'`col` = 123',
				['col' => 123],
			],
			[
				'`col` = 123.4',
				['col' => 123.4],
			],
			[
				'`col` = "x"',
				['col' => 'x'],
			],
			[
				'`col` = DT',
				['col' => new DateTime('2014-05-01')],
			],
			[
				'`col` IS NULL',
				['col' => null],
			],
			[
				'`col` IN (1, 2, 3)',
				['col' => [1, 2, 3]],
			],
			[
				'`col` IN ((1, 2), (3, 4))',
				['col' => [[1, 2], [3, 4]]],
			],
			[
				'`col` IN ("x", (1, ("x", DT), 3))',
				['col' => ['x', [1, ['x', new DateTime('2014-05-01')], 3]]],
			],
		];
	}


	public function provideExplicitTypesData()
	{
		return [
			[
				'`col` = 123',
				['col%i' => 123],
			],
			[
				'`col` = 123.4',
				['col%f' => 123.4],
			],
			[
				'`col` = "x"',
				['col%s' => 'x'],
			],
			[
				'`col` = DT',
				['col%dt' => new DateTime('2014-05-01')],
			],
			[
				'`col` IS NULL',
				['col%?i' => null],
			],
			[
				'`col` IN (1, 2, 3)',
				['col%i[]' => [1, 2, 3]],
			],
			[
				'`col` IN ((1, 2), (3, 4))',
				['col%i[][]' => [[1, 2], [3, 4]]],
			],
			[
				'`col` = `col`',
				['col%column' => 'col'],
			],
			[
				'`col` = NOW() + 5',
				['col%ex' => ['NOW() + %i', 5]],
			],
			[
				'`col` = NOW() + %i',
				['col%raw' => 'NOW() + %i'],
			],
		];
	}


	public function testAssoc()
	{
		$this->platform->shouldReceive('formatIdentifier')->once()->with('a')->andReturn('A');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('b.c')->andReturn('BC');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('d')->andReturn('D');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('e')->andReturn('E');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('f')->andReturn('F');

		$this->platform->shouldReceive('formatString')->once()->with(1)->andReturn("'1'");
		$this->platform->shouldReceive('formatString')->twice()->with('a')->andReturn("'a'");

		Assert::same(
			'A = 1 AND BC = 2 AND D IS NULL AND E IN (\'1\', \'a\') AND F IN (1, \'a\')',
			$this->parser->processModifier('and', [
				'a%i' => '1',
				'b.c' => 2,
				'd%?s' => null,
				'e%s[]' => ['1', 'a'],
				'f%any' => [1, 'a'],
			])
		);
	}


	public function testComplex()
	{
		$this->platform->shouldReceive('formatIdentifier')->once()->with('a')->andReturn('a');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('b')->andReturn('b');

		Assert::same(
			'(a = 1 AND b IS NULL) OR a = 2 OR (a IS NULL AND b = 1) OR b = 3',
			$this->parser->processModifier('or', [
				['%and', ['a%?i' => 1, 'b%?i' => null]],
				'a' => 2,
				['%and', ['a%?i' => null, 'b%?i' => 1]],
				'b' => 3,
			])
		);
	}


	public function testEmptyConds()
	{
		Assert::same(
			'1=1',
			$this->parser->processModifier('and', [])
		);

		Assert::same(
			'1=1',
			$this->parser->processModifier('or', [])
		);
	}


	public function testMultiColumnOr()
	{
		$this->platform->shouldReceive('formatIdentifier')->once()->with('a')->andReturn('a');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('b')->andReturn('b');
		$this->platform->shouldReceive('isSupported')->once()->with(IPlatform::SUPPORT_MULTI_COLUMN_IN)->andReturn(true);

		Assert::same(
			'(a, b) IN ((1, 2), (2, 3), (3, 4))',
			$this->parser->processModifier('multiOr', [
				['a' => 1, 'b' => 2],
				['a' => 2, 'b' => 3],
				['a' => 3, 'b' => 4],
			])
		);

		$this->platform->shouldReceive('isSupported')->once()->with(IPlatform::SUPPORT_MULTI_COLUMN_IN)->andReturn(false);

		Assert::same(
			'(a = 1 AND b = 2) OR (a = 2 AND b = 3) OR (a = 3 AND b = 4)',
			$this->parser->processModifier('multiOr', [
				['a' => 1, 'b' => 2],
				['a' => 2, 'b' => 3],
				['a' => 3, 'b' => 4],
			])
		);

		$this->platform->shouldReceive('isSupported')->once()->with(IPlatform::SUPPORT_MULTI_COLUMN_IN)->andReturn(true);

		Assert::throws(function () {
			$this->parser->processModifier('multiOr', [
				['a%i' => 1, 'b' => 2],
				['a%i' => 'a', 'b' => 2],
			]);
		}, InvalidArgumentException::class, 'Modifier %i expects value to be int, string given.');

		$this->platform->shouldReceive('isSupported')->once()->with(IPlatform::SUPPORT_MULTI_COLUMN_IN)->andReturn(false);

		Assert::throws(function () {
			$this->parser->processModifier('multiOr', [
				['a%i' => 1, 'b' => 2],
				['a%i' => 'a', 'b' => 2],
			]);
		}, InvalidArgumentException::class, 'Modifier %i expects value to be int, string given.');
	}


	public function testMultiColumnOrWithFqn(): void
	{
		$this->platform->shouldReceive('formatIdentifier')->with('tbl')->andReturn('tbl');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('a')->andReturn('a');
		$this->platform->shouldReceive('formatIdentifier')->once()->with('b')->andReturn('b');
		$this->platform->shouldReceive('isSupported')->once()->with(IPlatform::SUPPORT_MULTI_COLUMN_IN)->andReturn(true);

		$aFqn = new Fqn('tbl', 'a');
		$bFqn = new Fqn('tbl', 'b');
		Assert::same(
			'(tbl.a, tbl.b) IN ((1, 2), (2, 3), (3, 4))',
			$this->parser->processModifier('multiOr', [
				[[$aFqn, 1], [$bFqn, 2]],
				[[$aFqn, 2], [$bFqn, 3]],
				[[$aFqn, 3], [$bFqn, 4]],
			])
		);

		$this->platform->shouldReceive('isSupported')->once()->with(IPlatform::SUPPORT_MULTI_COLUMN_IN)->andReturn(false);

		Assert::same(
			'(tbl.a = 1 AND tbl.b = 2) OR (tbl.a = 2 AND tbl.b = 3) OR (tbl.a = 3 AND tbl.b = 4)',
			$this->parser->processModifier('multiOr', [
				[[$aFqn, 1], [$bFqn, 2]],
				[[$aFqn, 2], [$bFqn, 3]],
				[[$aFqn, 3], [$bFqn, 4]],
			])
		);

		$this->platform->shouldReceive('isSupported')->once()->with(IPlatform::SUPPORT_MULTI_COLUMN_IN)->andReturn(true);

		Assert::throws(function () use ($aFqn, $bFqn) {
			$this->parser->processModifier('multiOr', [
				[[$aFqn, 1, '%i'], [$bFqn, 2]],
				[[$aFqn, 'a', '%i'], [$bFqn, 2]],
				[[$aFqn, 3, '%i'], [$bFqn, 4]],
			]);
		}, InvalidArgumentException::class, 'Modifier %i expects value to be int, string given.');

		$this->platform->shouldReceive('isSupported')->once()->with(IPlatform::SUPPORT_MULTI_COLUMN_IN)->andReturn(false);

		Assert::throws(function () use ($aFqn, $bFqn) {
			$this->parser->processModifier('multiOr', [
				[[$aFqn, 1, '%i'], [$bFqn, 2]],
				[[$aFqn, 'a', '%i'], [$bFqn, 2]],
				[[$aFqn, 3, '%i'], [$bFqn, 4]],
			]);
		}, InvalidArgumentException::class, 'Modifier %i expects value to be int, string given.');
	}


	/**
	 * @dataProvider provideInvalidData
	 */
	public function testInvalid($type, $value, $message)
	{
		$this->platform->shouldReceive('formatIdentifier')->andReturn('`col`');
		$this->platform->shouldIgnoreMissing();
		Assert::throws(
			function() use ($type, $value) {
				$this->parser->processModifier($type, $value);
			},
			InvalidArgumentException::class, $message
		);
	}


	public function provideInvalidData()
	{
		return [
			['and', 123, 'Modifier %and expects value to be array, integer given.'],
			['and', null, 'Modifier %and expects value to be array, NULL given.'],

			['and', ['s'], 'Modifier %and requires items with numeric index to be array, string given.'],
			['and', ['a%i' => 's'], 'Modifier %i expects value to be int, string given.'],
			['and', ['a%i[]' => 123], 'Modifier %i[] expects value to be array, integer given.'],
			['and', ['a' => new stdClass()], 'Modifier %any expects value to be pretty much anything, stdClass given.'],
			['and', ['a%foo' => 's'], 'Unknown modifier %foo.'],

			['?and', [], 'Modifier %and does not have %?and variant.'],
			['and[]', [], 'Modifier %and does not have %and[] variant.'],
			['?and[]', [], 'Modifier %and does not have %?and[] variant.'],

			['or', 123, 'Modifier %or expects value to be array, integer given.'],
			['or', null, 'Modifier %or expects value to be array, NULL given.'],

			['or', ['s'], 'Modifier %or requires items with numeric index to be array, string given.'],
			['or', ['a%i' => 's'], 'Modifier %i expects value to be int, string given.'],
			['or', ['a%i[]' => 123], 'Modifier %i[] expects value to be array, integer given.'],
			['or', ['a' => new stdClass()], 'Modifier %any expects value to be pretty much anything, stdClass given.'],
			['or', ['a%foo' => 's'], 'Unknown modifier %foo.'],

			['?or', [], 'Modifier %or does not have %?or variant.'],
			['or[]', [], 'Modifier %or does not have %or[] variant.'],
			['?or[]', [], 'Modifier %or does not have %?or[] variant.'],
		];
	}
}


$test = new SqlProcessorWhereTest();
$test->run();
