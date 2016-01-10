<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Platforms;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nextras\Dbal\Connection;


class CachedPlatform implements IPlatform
{
	/** @var IPlatform */
	private $platform;

	/** @var Cache */
	private $cache;


	public function __construct(Connection $connection, IStorage $storage)
	{
		$key = md5(json_encode($connection->getConfig()));
		$this->platform = $connection->getPlatform();
		$this->cache = new Cache($storage, "nextras.dbal.platform.$key");
	}


	public function getTables()
	{
		return $this->cache->load('tables', function () {
			return $this->platform->getTables();
		});
	}


	public function getColumns($table)
	{
		return $this->cache->load('column.' . md5($table), function () use ($table) {
			return $this->platform->getColumns($table);
		});
	}


	public function getForeignKeys($table)
	{
		return $this->cache->load('fk.' . md5($table), function () use ($table) {
			return $this->platform->getForeignKeys($table);
		});
	}


	public function clearCache()
	{
		$this->cache->clean();
	}
}
