<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use Nextras\Dbal\Exception\MemberAccessException;


/**
 * @internal
 */
trait StrictObjectTrait
{
	/**
	 * @phpstan-return never
	 * @phpstan-param array<mixed> $args
	 * @throws MemberAccessException
	 */
	public function __call(string $name, array $args)
	{
		$class = get_class($this);
		throw new MemberAccessException("Call to undefined method $class::$name().");
	}


	/**
	 * @phpstan-return never
	 * @phpstan-param array<mixed> $args
	 * @throws MemberAccessException
	 */
	public static function __callStatic(string $name, array $args)
	{
		$class = get_class();
		throw new MemberAccessException("Call to undefined static method $class::$name().");
	}


	/**
	 * @phpstan-return never
	 * @throws MemberAccessException
	 */
	public function &__get(string $name)
	{
		$class = get_class();
		throw new MemberAccessException("Cannot read an undeclared property $class::\$$name.");
	}


	/**
	 * @param mixed $value
	 * @phpstan-return never
	 * @throws MemberAccessException
	 */
	public function __set(string $name, $value)
	{
		$class = get_class();
		throw new MemberAccessException("Cannot write to an undeclared property $class::\$$name.");
	}


	/**
	 * @phpstan-return never
	 * @throws MemberAccessException
	 */
	public function __unset(string $name)
	{
		$class = get_class();
		throw new MemberAccessException("Cannot unset an undeclared property $class::\$$name.");
	}


	public function __isset(string $name): bool
	{
		return false;
	}
}
