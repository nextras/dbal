<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\SymfonyBundle\DependencyInjection;


use Nextras\Dbal\Bridges\SymfonyBundle\DataCollector\QueryDataCollector;
use Nextras\Dbal\Connection;
use Nextras\Dbal\IConnection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;


class NextrasDbalExtension extends Extension
{
	/**
	 * @param array<mixed> $configs
	 */
	public function load(array $configs, ContainerBuilder $container): void
	{
		$configuration = new Configuration((bool) $container->getParameter('kernel.debug'));
		$config = $this->processConfiguration($configuration, $configs);

		$defaultConnectionName = $config['default_connection'];
		foreach ($config['connections'] as $name => $connectionConfig) {
			$profiler = $connectionConfig['profiler'];
			$explain = $connectionConfig['profilerExplain'];
			$maxQueries = $config['max_queries'];
			$this->loadConnection(
				$container,
				$name,
				$connectionConfig,
				$name === $defaultConnectionName,
				$profiler,
				$explain,
				$maxQueries,
			);
		}
	}


	/**
	 * @param array<mixed> $config
	 */
	private function loadConnection(
		ContainerBuilder $container,
		string $name,
		array $config,
		bool $isDefault,
		bool $profiler,
		bool $explain,
		int $maxQueries,
	): void
	{
		$config['sqlProcessorFactory'] = new Reference(
			"nextras_dbal.$name.sqlProcessorFactory",
			ContainerInterface::NULL_ON_INVALID_REFERENCE,
		);

		$connectionDefinition = new Definition(Connection::class);
		$connectionDefinition->setArgument('$config', $config);
		$connectionDefinition->setPublic(true);

		$container->addDefinitions([
			"nextras_dbal.$name.connection" => $connectionDefinition,
		]);

		if ($isDefault) {
			$container->setAlias(IConnection::class, "nextras_dbal.$name.connection")
				->setPublic(true);
		}

		if ($profiler) {
			$collectorName = "nextras_dbal.$name.query_data_collector";
			$collectorDefinition = new Definition(QueryDataCollector::class);
			$collectorDefinition->setArgument('$connection', new Reference("nextras_dbal.$name.connection"));
			$collectorDefinition->setArgument('$explain', $explain);
			$collectorDefinition->setArgument('$name', $collectorName);
			$collectorDefinition->setArgument('$maxQueries', $maxQueries);
			$collectorDefinition->addTag('data_collector', [
				'template' => '@NextrasDbal/DataCollector/template.html.twig',
				'id' => $collectorName,
			]);

			$container->addDefinitions([
				$collectorName => $collectorDefinition,
			]);

			$connectionDefinition->addMethodCall('addLogger', [new Reference($collectorName)]);
		}
	}
}
