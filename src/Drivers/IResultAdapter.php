<?php

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
	 * @param  int $index
	 * @throws InvalidStateException
	 */
	public function seek($index);


	/**
	 * @return array
	 */
	public function fetch();


	/**
	 * Returns rowset set column types, array of [type, nativeType]
	 * @return array
	 */
	public function getTypes();
}
