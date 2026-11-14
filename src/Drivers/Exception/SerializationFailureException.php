<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


class SerializationFailureException extends QueryException implements RetryableException
{
}
