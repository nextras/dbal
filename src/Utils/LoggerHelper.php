<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use Nextras\Dbal\Drivers\Exception\DriverException;
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
				$result
			);
			return $result;
		} catch (DriverException $exception) {
			$logger->onQueryException(
				$sqlQuery,
				$driver->getQueryElapsedTime(),
				$exception
			);
			throw $exception;
		}
	}
}
