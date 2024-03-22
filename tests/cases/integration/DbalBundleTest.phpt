<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Bridges\SymfonyBundle\DependencyInjection\NextrasDbalExtension;
use Nextras\Dbal\Connection;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ISqlProcessorFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class DbalBundleTest extends IntegrationTestCase
{
	public function testDic()
	{
		$extension = new NextrasDbalExtension();

		$containerBuilder = new ContainerBuilder();
		$containerBuilder->setParameter('kernel.debug', true);
		$containerBuilder->registerExtension($extension);
		$containerBuilder->loadFromExtension($extension->getAlias(), [
			'connections' => [
				'default' => [
					'driver' => 'mysqli',
					'host' => '127.0.0.1',
					'username' => 'username',
					'password' => 'password',
				],
			],
		]);
		$containerBuilder->addDefinitions([
			'nextras_dbal.default.sqlProcessorFactory' => new Definition(SqlProcessorFactory::class),
		]);

		$containerBuilder->compile();

		$dicFile = TEMP_DIR . '/symfony_dic_' . uniqid() . '.php';
		$dicClass = 'SymfonyDic' . uniqid();

		$dumper = new PhpDumper($containerBuilder);
		file_put_contents($dicFile, $dumper->dump(['class' => $dicClass]));
		require_once $dicFile;

		/** @var \Symfony\Component\DependencyInjection\Container $container */
		$container = new $dicClass;

		$connection = $container->get('nextras_dbal.default.connection');
		Assert::type(Connection::class, $connection);

		$connection = $container->get(IConnection::class);
		Assert::type(Connection::class, $connection);

		Assert::type(ISqlProcessorFactory::class, $connection->getConfig()["sqlProcessorFactory"]);
	}
}


$test = new DbalBundleTest();
$test->run();
