<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteCaching;

use Nette\Caching\Cache;
use Nextras\Dbal\Platforms\IPlatform;


class CachedPlatform implements IPlatform
{
	private const CACHE_VERSION = 'v2';

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


	public function getTables(?string $schema = null): array
	{
		return $this->cache->load(self::CACHE_VERSION . '.tables.' . $schema , function () use ($schema) {
			return $this->platform->getTables($schema);
		});
	}


	public function getColumns(string $table): array
	{
		return $this->cache->load(self::CACHE_VERSION . '.columns.' . $table, function () use ($table) {
			return $this->platform->getColumns($table);
		});
	}


	public function getForeignKeys(string $table): array
	{
		return $this->cache->load(self::CACHE_VERSION . '.foreign_keys.' . $table, function () use ($table) {
			return $this->platform->getForeignKeys($table);
		});
	}


	public function getPrimarySequenceName(string $table): ?string
	{
		return $this->cache->load(self::CACHE_VERSION . '.sequence.' . $table, function () use ($table) {
			return [$this->platform->getPrimarySequenceName($table)];
		})[0];
	}


	public function isSupported(int $feature): bool
	{
		return $this->platform->isSupported($feature);
	}


	public function clearCache(): void
	{
		$this->cache->clean();
	}
}
