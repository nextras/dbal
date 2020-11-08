<?php declare(strict_types = 1);

namespace Nextras\Dbal\Result;


use Nextras\Dbal\Exception\InvalidArgumentException;


interface IResultAdapter
{
	public const TYPE_DRIVER_SPECIFIC = 1;
	public const TYPE_STRING = 2;
	public const TYPE_INT = 4;
	public const TYPE_FLOAT = 8;
	public const TYPE_BOOL = 16;
	public const TYPE_DATETIME = 32;
	public const TYPE_AS_IS = 64;


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
	 * @throws InvalidArgumentException
	 */
	public function seek(int $index): void;


	/**
	 * @phpstan-return array<mixed>|null
	 */
	public function fetch(): ?array;


	/**
	 * Returns row's column types, array of [type, nativeType]
	 * @phpstan-return array<string, array{int, mixed}>
	 */
	public function getTypes(): array;


	public function getRowsCount(): int;
}
