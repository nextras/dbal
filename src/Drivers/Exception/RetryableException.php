<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


/**
 * A concurrency failure for which an application may retry the entire transaction after successful cleanup.
 * Retrying a statement or commit alone is insufficient. ORM state and external side effects must be handled by the caller.
 */
interface RetryableException extends \Throwable
{
}
