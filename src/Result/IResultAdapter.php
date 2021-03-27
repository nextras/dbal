<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use Nextras\Dbal\Exception\InvalidArgumentException;


interface IResultAdapter
{
	/**
	 * Converts result adapter to buffered version.
	 * @internal
	 */
	public function toBuffered(): IResultAdapter;


	/**
	 * Converts result adapter to not explicitly buffered version.
	 * The resulting adapter may be naturally buffered by PHP's extension implementation.
	 * @internal
	 */
	public function toUnbuffered(): IResultAdapter;


	/**
	 * Seeks the result of specific position. Throws if position does not exist.
	 * @throws InvalidArgumentException
	 */
	public function seek(int $index): void;


	/**
	 * Returns next unfetched row. Returns a null if there is no unfetched row.
	 * @phpstan-return array<mixed>|null
	 */
	public function fetch(): ?array;


	/**
	 * Returns number of row in Result.
	 */
	public function getRowsCount(): int;


	/**
	 * Returns Result's column types as map of column name and native driver type.
	 * @phpstan-return array<string, mixed>
	 */
	public function getTypes(): array;


	/**
	 * Returns driver specific normalizers.
	 * @phpstan-return array<string, callable(mixed): mixed>
	 */
	public function getNormalizers(): array;
}
