<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


class Column
{
	use StrictObjectTrait;


	public function __construct(
		public readonly string $name,
		public readonly string $type,
		public readonly ?int $size,
		public readonly ?string $default,
		public readonly bool $isPrimary,
		public readonly bool $isAutoincrement,
		public readonly bool $isUnsigned,
		public readonly bool $isNullable,
		/** @var array<string, mixed> */
		public readonly array $meta = [],
	)
	{
	}
}
