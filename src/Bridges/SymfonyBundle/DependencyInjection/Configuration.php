<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\SymfonyBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use function array_key_exists;
use function is_array;


class Configuration implements ConfigurationInterface
{
	public function __construct(private readonly bool $debug)
	{
	}


	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder('nextras_dbal');

		// @formatter:off
		$treeBuilder->getRootNode()
			->beforeNormalization()
				->ifTrue(static fn($v): bool => is_array($v) && !array_key_exists('connections', $v))
				->then(static fn($v): array => [
						'connections' => ['default' => $v],
						'default_connection' => 'default',
					])
			->end()
			->children()
				->integerNode('max_queries')
					->defaultValue(100)
				->end()
				->scalarNode('default_connection')
					->defaultValue('default')
				->end()
				->arrayNode('connections')
					->useAttributeAsKey('name')
					->arrayPrototype()
						->ignoreExtraKeys(false)
						->children()
							->scalarNode('driver')
								->isRequired()
								->cannotBeEmpty()
							->end()
							->booleanNode('profiler')
								->defaultValue($this->debug)
							->end()
							->booleanNode('profilerExplain')
								->defaultTrue()
							->end()
						->end()
					->end()
				->end()
			->end();
		// @formatter:on

		return $treeBuilder;
	}
}
