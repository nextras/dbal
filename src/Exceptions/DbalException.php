<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace  Nextras\Dbal\Exceptions;

use Exception;
use Nextras\Dbal\Drivers\IDriverException;


class DbalException extends Exception
{

	public function __construct($message, IDriverException $exception = NULL)
	{
		$exception = $exception instanceof Exception ? $exception : NULL;
		parent::__construct($message, 0, $exception);
	}

}
