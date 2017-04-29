<?php declare(strict_types = 1);

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
	const TYPE_DRIVER_SPECIFIC = 1;
	const TYPE_STRING   = 2;
	const TYPE_INT      = 4;
	const TYPE_FLOAT    = 8;
	const TYPE_BOOL     = 16;
	const TYPE_DATETIME = 32;
	const TYPE_AS_IS    = 64;


	/**
	 * @throws InvalidStateException
	 */
	public function seek(int $index);


	/**
	 * @return array|null
	 */
	public function fetch();


	/**
	 * Returns rowset set column types, array of [type, nativeType]
	 */
	public function getTypes(): array;


	public function getRowsCount(): int;
}
