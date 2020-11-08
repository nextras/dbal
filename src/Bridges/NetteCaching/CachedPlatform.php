<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\NetteCaching;


use DateInterval;
use DateTimeInterface;
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


	/** @inheritDoc */
	public function getTables(?string $schema = null): array
	{
		return $this->cache->load(self::CACHE_VERSION . '.tables.' . $schema, function () use ($schema): array {
			return $this->platform->getTables($schema);
		});
	}


	/** @inheritDoc */
	public function getColumns(string $table): array
	{
		return $this->cache->load(self::CACHE_VERSION . '.columns.' . $table, function () use ($table): array {
			return $this->platform->getColumns($table);
		});
	}


	/** @inheritDoc */
	public function getForeignKeys(string $table): array
	{
		return $this->cache->load(self::CACHE_VERSION . '.foreign_keys.' . $table, function () use ($table): array {
			return $this->platform->getForeignKeys($table);
		});
	}


	public function getPrimarySequenceName(string $table): ?string
	{
		return $this->cache->load(self::CACHE_VERSION . '.sequence.' . $table, function () use ($table): array {
			return [$this->platform->getPrimarySequenceName($table)];
		})[0];
	}


	public function formatString(string $value): string
	{
		return $this->platform->formatString($value);
	}


	public function formatStringLike(string $value, int $mode)
	{
		return $this->platform->formatStringLike($value, $mode);
	}


	public function formatJson($value): string
	{
		return $this->platform->formatJson($value);
	}


	public function formatBool(bool $value): string
	{
		return $this->platform->formatBool($value);
	}


	public function formatIdentifier(string $value): string
	{
		return $this->platform->formatIdentifier($value);
	}


	public function formatDateTime(DateTimeInterface $value): string
	{
		return $this->platform->formatDateTime($value);
	}


	public function formatLocalDateTime(DateTimeInterface $value): string
	{
		return $this->platform->formatLocalDateTime($value);
	}


	public function formatDateInterval(DateInterval $value): string
	{
		return $this->platform->formatDateInterval($value);
	}


	public function formatBlob(string $value): string
	{
		return $this->platform->formatBlob($value);
	}


	public function formatLimitOffset(?int $limit, ?int $offset): string
	{
		return $this->platform->formatLimitOffset($limit, $offset);
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
