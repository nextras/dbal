<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


class Column
{
	use StrictObjectTrait;


	/** @var string */
	public $name;

	/** @var string */
	public $type;

	/** @var int|null */
	public $size;

	/** @var string|null */
	public $default;

	/** @var bool */
	public $isPrimary;

	/** @var bool */
	public $isAutoincrement;

	/** @var bool */
	public $isUnsigned;

	/** @var bool */
	public $isNullable;

	/**
	 * @var mixed[]
	 * @phpstan-var array<string, mixed>
	 */
	public $meta;
}
