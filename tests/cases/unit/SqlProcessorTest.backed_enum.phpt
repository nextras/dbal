<?php declare(strict_types = 1);

/**
 * @testCase
 * @phpVersion >= 8.1
 */

namespace NextrasTests\Dbal;


use Mockery;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


enum DirectionEnum: string
{
	case LEFT = 'left';
	case RIGHT = 'right';
}


enum BinaryEnum: int
{
	case ZERO = 0;
	case ONE = 1;
}


class SqlProcessorBackedEnumTest extends TestCase
{
	/** @var IPlatform|Mockery\MockInterface */
	private $platform;

	/** @var IDriver|Mockery\MockInterface */
	private $driver;

	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = Mockery::mock(IDriver::class);
		$this->platform = \Mockery::mock(IPlatform::class);
		$this->parser = new SqlProcessor($this->driver, $this->platform);
	}


	public function testString()
	{
		$cases = DirectionEnum::cases();
		foreach ($cases as $case) {
			$this->driver->shouldReceive('convertStringToSql')->once()->with($case->value)->andReturn('hit');
			Assert::same('hit', $this->parser->processModifier('s', $case));
		}

		$cases = BinaryEnum::cases();
		foreach ($cases as $case) {
			Assert::exception(
				function () use ($case) {
					$this->parser->processModifier('s', $case);
				},
				InvalidArgumentException::class,
				'Modifier %s expects value to be string, integer given.'
			);
		}

	}


	public function testStringArray()
	{

		$cases = DirectionEnum::cases();
		$this->driver->shouldReceive('convertStringToSql')->times(count($cases))
			->andReturnArg(0);
		Assert::same('(left, right)', $this->parser->processModifier('s[]', $cases));

		Assert::exception(
			function () {
				$cases = BinaryEnum::cases();
				$this->parser->processModifier('s[]', $cases);
			},
			InvalidArgumentException::class,
			'Modifier %s expects value to be string, integer given.'
		);

	}


	public function testInt()
	{
		$cases = DirectionEnum::cases();
		foreach ($cases as $case) {
			Assert::exception(
				function () use ($case) {
					$this->parser->processModifier('i', $case);
				},
				InvalidArgumentException::class,
				'Modifier %i expects value to be int, string given.'
			);
		}

		$cases = BinaryEnum::cases();
		foreach ($cases as $case) {
			Assert::same((string) $case->value, $this->parser->processModifier('i', $case));
		}

	}


	public function testIntArray()
	{
		$cases = DirectionEnum::cases();
		Assert::exception(
			function () use ($cases) {
				$this->parser->processModifier('i[]', $cases);
			},
			InvalidArgumentException::class,
			'Modifier %i expects value to be int, string given.'
		);

		$cases = BinaryEnum::cases();
		Assert::same('(0, 1)', $this->parser->processModifier('i[]', $cases));

	}
}


$test = new SqlProcessorBackedEnumTest();
$test->run();
