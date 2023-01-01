<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


class Column
{
	use StrictObjectTrait;


	public string $name;
	public string $type;
	public ?int $size;
	public ?string $default;
	public bool $isPrimary;
	public bool $isAutoincrement;
	public bool $isUnsigned;
	public bool $isNullable;
	/**
	 * @var mixed[]
	 * @phpstan-var array<string, mixed>
	 */
	public array $meta = [];
}
