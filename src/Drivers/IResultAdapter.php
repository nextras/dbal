<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\Dbal\Exception\InvalidArgumentException;


interface IResultAdapter
{
	/** @const field types */
	const TYPE_DRIVER_SPECIFIC = 1;
	const TYPE_STRING   = 2;
	const TYPE_INT      = 4;
	const TYPE_FLOAT    = 8;
	const TYPE_BOOL     = 16;
	const TYPE_DATETIME = 32;
	const TYPE_AS_IS    = 64;


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
