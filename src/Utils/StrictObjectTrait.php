<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use Nextras\Dbal\Exception\MemberAccessException;


/**
 * @internal
 */
trait StrictObjectTrait
{
	/**
	 * @param array<mixed> $args
	 * @return never
	 * @throws MemberAccessException
	 */
	public function __call(string $name, array $args)
	{
		$class = $this::class;
		throw new MemberAccessException("Call to undefined method $class::$name().");
	}


	/**
	 * @param array<mixed> $args
	 * @return never
	 * @throws MemberAccessException
	 */
	public static function __callStatic(string $name, array $args)
	{
		$class = self::class;
		throw new MemberAccessException("Call to undefined static method $class::$name().");
	}


	/**
	 * @return never
	 * @throws MemberAccessException
	 */
	public function &__get(string $name)
	{
		$class = self::class;
		throw new MemberAccessException("Cannot read an undeclared property $class::\$$name.");
	}


	/**
	 * @return never
	 * @throws MemberAccessException
	 */
	public function __set(string $name, mixed $value)
	{
		$class = self::class;
		throw new MemberAccessException("Cannot write to an undeclared property $class::\$$name.");
	}


	/**
	 * @return never
	 * @throws MemberAccessException
	 */
	public function __unset(string $name)
	{
		$class = self::class;
		throw new MemberAccessException("Cannot unset an undeclared property $class::\$$name.");
	}


	public function __isset(string $name): bool
	{
		return false;
	}
}
