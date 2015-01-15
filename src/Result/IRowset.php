<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Nextras\Dbal\Exceptions\DbalException;
use Traversable;


interface IRowset extends Traversable
{

	/**
	 * @param  int $index
	 * @throws DbalException
	 */
	public function seek($index);


	/**
	 * @return IRow
	 */
	public function fetch();

}
