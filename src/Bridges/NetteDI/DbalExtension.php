<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteDI;

use Nette\DI\CompilerExtension;


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

		$builder->addDefinition($this->prefix('connection'))
			->setClass('Nextras\Dbal\Connection')
			->setArguments([
				'config' => $config,
			]);
	}

}
