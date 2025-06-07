<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\NetteCaching;


use DateInterval;
use DateTimeInterface;
use Nette\Caching\Cache;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\MultiQueryParser\IMultiQueryParser;


class CachedPlatform implements IPlatform
{
	private const CACHE_VERSION = 'v5';


	public function __construct(
		private readonly IPlatform $platform,
		private readonly Cache $cache,
	)
	{
	}


	public function getName(): string
	{
		return $this->platform->getName();
	}


	public function getTables(?string $schema = null): array
	{
		return $this->cache->load(
			self::CACHE_VERSION . '.tables.' . $schema,
			fn(): array => $this->platform->getTables($schema),
		);
	}


	public function getColumns(string $table, ?string $schema = null): array
	{
		return $this->cache->load(
			self::CACHE_VERSION . '.columns.' . $table . $schema,
			fn(): array => $this->platform->getColumns($table, $schema),
		);
	}


	public function getForeignKeys(string $table, ?string $schema = null): array
	{
		return $this->cache->load(
			self::CACHE_VERSION . '.foreign_keys.' . $table . $schema,
			fn(): array => $this->platform->getForeignKeys($table, $schema),
		);
	}


	public function getPrimarySequenceName(string $table, ?string $schema = null): ?string
	{
		return $this->cache->load(
			self::CACHE_VERSION . '.sequence.' . $table . $schema,
			fn(): array => [$this->platform->getPrimarySequenceName($table, $schema)],
		)[0];
	}


	public function formatString(string $value): string
	{
		return $this->platform->formatString($value);
	}


	public function formatStringLike(string $value, int $mode)
	{
		return $this->platform->formatStringLike($value, $mode);
	}


	public function formatJson(mixed $value): string
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


	public function formatLocalDate(DateTimeInterface $value): string
	{
		return $this->platform->formatLocalDate($value);
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


	public function createMultiQueryParser(): IMultiQueryParser
	{
		return $this->platform->createMultiQueryParser();
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
