<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\Dbal\InvalidStateException;


interface IResultAdapter
{
	/** @const field types */
	public const TYPE_DRIVER_SPECIFIC = 1;
	public const TYPE_STRING   = 2;
	public const TYPE_INT      = 4;
	public const TYPE_FLOAT    = 8;
	public const TYPE_BOOL     = 16;
	public const TYPE_DATETIME = 32;
	public const TYPE_AS_IS    = 64;


	/**
	 * @throws InvalidStateException
	 */
	public function seek(int $index): void;


	public function fetch(): ?array;


	/**
	 * Returns rowset set column types, array of [type, nativeType]
	 */
	public function getTypes(): array;
}
