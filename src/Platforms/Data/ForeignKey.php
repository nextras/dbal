<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


class ForeignKey
{
	use StrictObjectTrait;


	/** @var string */
	public $name;

	/** @var string */
	public $schema;

	/** @var string */
	public $column;

	/** @var string */
	public $refTable;

	/** @var string */
	public $refTableSchema;

	/** @var string */
	public $refColumn;


	public function getNameFqn(): string
	{
		return "$this->schema.$this->name";
	}


	public function getRefTableFqn(): string
	{
		return "$this->refTableSchema.$this->refTable";
	}
}
