<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Result;

use Traversable;


interface IRowset extends Traversable
{

	/**
	 * @return Row
	 */
	public function fetch();

}
