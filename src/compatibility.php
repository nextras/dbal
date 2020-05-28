<?php /** @noinspection PhpMultipleClassesDeclarationsInOneFile */
declare(strict_types = 1);

namespace Nextras\Dbal;


use Exception;


if (false) {
	/** @deprecated use Nextras\Dbal\Drivers\Exception\ConnectionException */
	class ConnectionException extends Exception
	{
	}
} elseif (!class_exists(ConnectionException::class)) {
	class_alias(\Nextras\Dbal\Drivers\Exception\ConnectionException::class, ConnectionException::class);
}

if (false) {
	/** @deprecated use Nextras\Dbal\Drivers\Exception\ConstraintViolationException */
	class ConstraintViolationException extends Exception
	{
	}
} elseif (!class_exists(ConstraintViolationException::class)) {
	class_alias(\Nextras\Dbal\Drivers\Exception\ConstraintViolationException::class, ConstraintViolationException::class);
}

if (false) {
	/** @deprecated use Nextras\Dbal\Drivers\Exception\DriverException */
	class DriverException extends Exception
	{
	}
} elseif (!class_exists(DriverException::class)) {
	class_alias(\Nextras\Dbal\Drivers\Exception\DriverException::class, DriverException::class);
}

if (false) {
	/** @deprecated use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException */
	class ForeignKeyConstraintViolationException extends Exception
	{
	}
} elseif (!class_exists(ForeignKeyConstraintViolationException::class)) {
	class_alias(\Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException::class, ForeignKeyConstraintViolationException::class);
}

if (false) {
	/** @deprecated use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException */
	class NotNullConstraintViolationException extends Exception
	{
	}
} elseif (!class_exists(NotNullConstraintViolationException::class)) {
	class_alias(\Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException::class, NotNullConstraintViolationException::class);
}

if (false) {
	/** @deprecated use Nextras\Dbal\Drivers\Exception\QueryException */
	class QueryException extends Exception
	{
	}
} elseif (!class_exists(QueryException::class)) {
	class_alias(\Nextras\Dbal\Drivers\Exception\QueryException::class, QueryException::class);
}

if (false) {
	/** @deprecated use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException */
	class UniqueConstraintViolationException extends Exception
	{
	}
} elseif (!class_exists(UniqueConstraintViolationException::class)) {
	class_alias(\Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException::class, UniqueConstraintViolationException::class);
}

if (false) {
	/** @deprecated use Nextras\Dbal\Exception\IOException */
	class IOException extends Exception
	{
	}
} elseif (!class_exists(IOException::class)) {
	class_alias(\Nextras\Dbal\Exception\IOException::class, IOException::class);
}
