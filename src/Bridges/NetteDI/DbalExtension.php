<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteDI;

use Nette\DI\CompilerExtension;
use Tracy\Debugger;


class DbalExtension extends CompilerExtension
{

	public function loadConfiguration()
	{
		$config = $this->getConfig();
		$this->setupConnection($config);
	}


	protected function setupConnection(array $config)
	{
		$builder = $this->getContainerBuilder();

		$definition = $builder->addDefinition($this->prefix('connection'))
			->setClass('Nextras\Dbal\Connection')
			->setArguments([
				'config' => $config,
			]);

		if (isset($config['debugger'])) {
			$debugger = $config['debugger'];
		} else {
			$debugger = class_exists('Tracy\Debugger', FALSE) && Debugger::$productionMode === Debugger::DEVELOPMENT;
		}

		if ($debugger) {
			$definition->addSetup('@Tracy\BlueScreen::addPanel', ['Nextras\Dbal\Bridges\NetteTracy\BluescreenQueryPanel::renderBluescreenPanel']);
			$definition->addSetup('Nextras\Dbal\Bridges\NetteTracy\ConnectionPanel::install', ['@self']);
		}
	}

}
