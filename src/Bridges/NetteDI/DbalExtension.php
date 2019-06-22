<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteDI;

use Nette\DI\CompilerExtension;
use Nextras\Dbal\Bridges\NetteTracy\BluescreenQueryPanel;
use Nextras\Dbal\Bridges\NetteTracy\ConnectionPanel;
use Nextras\Dbal\Connection;
use Tracy\Debugger;


class DbalExtension extends CompilerExtension
{
	public function loadConfiguration()
	{
		$config = $this->getConfig();
		\assert(is_array($config));
		$this->setupConnection($config);
	}


	protected function setupConnection(array $config)
	{
		$builder = $this->getContainerBuilder();

		$definition = $builder->addDefinition($this->prefix('connection'))
			->setType(Connection::class)
			->setArguments([
				'config' => $config,
			])
			->setAutowired(isset($config['autowired']) ? $config['autowired'] : true);

		if (isset($config['debugger'])) {
			$debugger = $config['debugger'];
		} else {
			$debugger = class_exists('Tracy\Debugger', false) && Debugger::$productionMode === Debugger::DEVELOPMENT;
		}

		if ($debugger) {
			$definition->addSetup('@Tracy\BlueScreen::addPanel', [BluescreenQueryPanel::class . '::renderBluescreenPanel']);
			$definition->addSetup(ConnectionPanel::class . '::install', ['@self', $config['panelQueryExplain'] ?? true]);
		}
	}
}
