<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


class ForeignKey
{
	use StrictObjectTrait;


	public function __construct(
		public readonly Fqn $fqnName,
		public readonly string $column,
		public readonly Fqn $refTable,
		public readonly string $refColumn,
	)
	{
	}
}
