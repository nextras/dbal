<?php declare(strict_types = 1);

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
		if ($this->schema === '') {
			return $this->name;
		} else {
			return "$this->schema.$this->name";
		}
	}


	public function getRefTableFqn(): string
	{
		if ($this->refTableSchema === '') {
			return $this->refTable;
		} else {
			return "$this->refTableSchema.$this->refTable";
		}
	}
}
