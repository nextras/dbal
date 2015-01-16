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

	/**
	 * @param  int $index
	 * @throws DbalException
	 */
	public function seek($index);


	/**
	 * @return array
	 */
	public function fetch();

}
