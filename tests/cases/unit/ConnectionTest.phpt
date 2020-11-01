<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\SqlProcessor;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../bootstrap.php';


class ConnectionTest extends TestCase
{
	public function testQueryArgs()
	{
		$config = [];
		$config['driver'] = $driver = \Mockery::spy(IDriver::class);
		$config['sqlProcessorFactory'] = $factory = \Mockery::mock(ISqlProcessorFactory::class);

		$sqlProcessor = \Mockery::mock(SqlProcessor::class);
		$factory->shouldReceive('create')->with(\Mockery::type(Connection::class))->once()->andReturn($sqlProcessor);
		$driver->shouldReceive('isConnected')->once()->andReturn(false);

		$connection = new Connection($config);

		$sqlProcessor->shouldReceive('process')->once()->with(['SELECT * FROM %table', 'table'])->andReturn('query');
		$connection->queryArgs(['SELECT * FROM %table', 'table']);

		Environment::$checkAssertions = false;
	}
}


$test = new ConnectionTest();
$test->run();
