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


require_once __DIR__ . '/../../bootstrap.php';


class ConnectionSqlProcessorFactoryTest extends TestCase
{
	public function testFactory()
	{
		$config = [];
		$config['driver'] = $driver = \Mockery::mock(IDriver::class);
		$config['sqlProcessorFactory'] = $factory = \Mockery::mock(ISqlProcessorFactory::class);

		$factory->shouldReceive('create')->with(\Mockery::type(Connection::class))->once()->andReturn(\Mockery::mock(SqlProcessor::class));
		$driver->shouldReceive('isConnected')->once()->andReturn(false);

		$connection = new Connection($config);

		$factory->shouldReceive('create')->with(\Mockery::type(Connection::class))->once()->andReturn(\Mockery::mock(SqlProcessor::class));
		$driver->shouldReceive('connect')->once();
		$connection->reconnectWithConfig($config);

		Assert::true(true);
	}
}


$test = new ConnectionSqlProcessorFactoryTest();
$test->run();
