<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\SymfonyBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use function array_key_exists;
use function is_array;


class Configuration implements ConfigurationInterface
{
	/** @var bool */
	private $debug;


	public function __construct(bool $debug)
	{
		$this->debug = $debug;
	}


	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder('nextras_dbal');

		// @formatter:off
		$treeBuilder->getRootNode()
			->beforeNormalization()
				->ifTrue(static function ($v): bool {
					return is_array($v) && !array_key_exists('connections', $v);
				})
				->then(static function ($v): array {
					return [
						'connections' => ['default' => $v],
						'default_connection' => 'default',
					];
				})
			->end()
			->children()
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
