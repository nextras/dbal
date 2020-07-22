<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\NetteDI;


use Nette\DI\CompilerExtension;
use Nextras\Dbal\Bridges\NetteTracy\BluescreenQueryPanel;
use Nextras\Dbal\Bridges\NetteTracy\ConnectionPanel;
use Nextras\Dbal\Connection;
use Tracy\Debugger;


class DbalExtension extends CompilerExtension
{
	public function loadConfiguration(): void
	{
		$config = $this->getConfig();
		\assert(is_array($config));
		$this->setupConnection($config);
	}


	/**
	 * @phpstan-param array<mixed> $config
	 */
	protected function setupConnection(array $config): void
	{
		$builder = $this->getContainerBuilder();

		$definition = $builder->addDefinition($this->prefix('connection'))
			->setType(Connection::class)
			->setArguments([
				'config' => $config,
			])
			->setAutowired(isset($config['autowired']) ? $config['autowired'] : true);

		if (isset($config['debugger'])) {
			$debugger = (bool) $config['debugger'];
		} else {
			$debugger = class_exists('Tracy\Debugger', false) && Debugger::$productionMode === Debugger::DEVELOPMENT;
		}

		if ($debugger) {
			$definition->addSetup('@Tracy\BlueScreen::addPanel', [BluescreenQueryPanel::class . '::renderBluescreenPanel']);
			$definition->addSetup(
				ConnectionPanel::class . '::install',
				['@self', $config['panelQueryExplain'] ?? true]
			);
		}
	}
}
