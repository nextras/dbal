<?php declare(strict_types = 1);

namespace Nextras\Dbal;


interface ISqlProcessorModifierResolver
{
	/**
	 * Resolves the passed value to a modifier name (without a `%` prefix character).
	 * If not resolved, return a null to let other resolvers continue.
	 */
	public function resolve(mixed $value): ?string;
}
