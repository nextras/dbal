<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


class LockWaitTimeoutException extends QueryException implements RetryableException
{
}
