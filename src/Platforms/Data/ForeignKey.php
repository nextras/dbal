<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


class ForeignKey
{
	use StrictObjectTrait;


	public string $name;
	public string $schema;
	public string $column;
	public string $refTable;
	public string $refTableSchema;
	public string $refColumn;


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
