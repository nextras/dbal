<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers;

use Nextras\Dbal\Exceptions\DbalException;


interface IRowsetAdapter
{
	/** @const field types */
	const TYPE_DRIVER_SPECIFIC = 0;
	const TYPE_STRING   = 1;
	const TYPE_INT      = 2;
	const TYPE_FLOAT    = 3;
	const TYPE_BOOL     = 4;
	const TYPE_DATETIME = 5;


	/**
	 * @param  int $index
	 * @throws DbalException
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
