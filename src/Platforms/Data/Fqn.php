<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms\Data;


/**
 * Fully qualified name/identifier
 */
class Fqn
{
	public function __construct(
		public readonly string $name,
		public readonly string $schema,
	)
	{
	}


	public function getUnescaped(): string
	{
		if ($this->schema === '') {
			return $this->name;
		} else {
			return "$this->schema.$this->name";
		}
	}
}
