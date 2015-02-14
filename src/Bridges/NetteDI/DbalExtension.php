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

		if (isset($config['tracyPanel'])) {
			$enableTracyPanel = $config['tracyPanel'];
		} else {
			$enableTracyPanel = class_exists('Tracy\Debugger') && Debugger::$productionMode === Debugger::DEVELOPMENT;
		}

		if ($enableTracyPanel) {
			$definition->addSetup('Nextras\Dbal\Bridges\NetteTracy\ConnectionPanel::install', ['@self']);
		}
	}

}
