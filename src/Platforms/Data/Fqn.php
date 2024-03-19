<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


/**
 * Fully qualified name/identifier
 */
class Fqn
{
	use StrictObjectTrait;


	public function __construct(
		public readonly string $schema,
		public readonly string $name,
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
