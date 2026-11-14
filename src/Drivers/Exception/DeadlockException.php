<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


class DeadlockException extends QueryException implements RetryableException
{
}
