<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery\MockInterface;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlProcessorIdentifiersTest extends TestCase
{
	/** @var MockInterface */
	private $driver;

	/** @var SqlProcessor */
	private $parser;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = \Mockery::mock(IDriver::class);
		$this->parser = new SqlProcessor($this->driver);
	}


	public function testBasic()
	{
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('a')->andReturn('`a`');
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('b.c')->andReturn('`b`.`c`');
		$this->driver->shouldReceive('convertIdentifierToSql')->once()->with('d.e')->andReturn('`d`.`e`');

		Assert::same(
			'SELECT `a`, `b`.`c` FROM `d`.`e`',
			$this->parser->process(['SELECT [a], [b.c] FROM [d.e]'])
		);
	}
}


$test = new SqlProcessorIdentifiersTest();
$test->run();
