<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Platforms;

use Nette\Caching\Cache;


class CachedPlatform implements IPlatform
{
	/** @var IPlatform */
	private $platform;

	/** @var Cache */
	private $cache;


	public function __construct(IPlatform $platform, Cache $cache)
	{
		$this->platform = $platform;
		$this->cache = $cache;
	}


	public function getName(): string
	{
		return $this->platform->getName();
	}


	public function getTables(): array
	{
		return $this->cache->load('tables', function () {
			return $this->platform->getTables();
		});
	}


	public function getColumns(string $table): array
	{
		return $this->cache->load('columns.' . $table, function () use ($table) {
			return $this->platform->getColumns($table);
		});
	}


	public function getForeignKeys(string $table): array
	{
		return $this->cache->load('foreign_keys.' . $table, function () use ($table) {
			return $this->platform->getForeignKeys($table);
		});
	}


	public function getPrimarySequenceName(string $table): ?string
	{
		return $this->cache->load('sequence.' . $table, function () use ($table) {
			return [$this->platform->getPrimarySequenceName($table)];
		})[0];
	}


	public function isSupported(int $feature): bool
	{
		return $this->platform->isSupported($feature);
	}


	public function clearCache()
	{
		$this->cache->clean();
	}
}
