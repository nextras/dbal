<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;

use Nextras\Dbal\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Result\Result;


/**
 * @internal
 */
class LoggerHelper
{
	use StrictObjectTrait;


	/**
	 * @throws DriverException
	 */
	public static function loggedQuery(IDriver $driver, ILogger $logger, string $sqlQuery): Result
	{
		try {
			$result = $driver->query($sqlQuery);
			$logger->onQuery(
				$sqlQuery,
				$driver->getQueryElapsedTime(),
				$result,
				null // exception
			);
			return $result;
		} catch (DriverException $exception) {
			$logger->onQuery(
				$sqlQuery,
				$driver->getQueryElapsedTime(),
				null, // result
				$exception
			);
			throw $exception;
		}
	}
}
