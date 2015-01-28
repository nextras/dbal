<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace  Nextras\Dbal\Exceptions;

use Exception;
use Nextras\Dbal\Drivers\DriverException;


class DbalException extends Exception
{

	public function __construct($message, DriverException $exception = NULL)
	{
		parent::__construct($message, 0, $exception);
	}

}
