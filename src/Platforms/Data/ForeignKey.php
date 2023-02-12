<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


class ForeignKey
{
	use StrictObjectTrait;


	public function __construct(
		public readonly string $name,
		public readonly string $schema,
		public readonly string $column,
		public readonly string $refTable,
		public readonly string $refTableSchema,
		public readonly string $refColumn,
	)
	{
	}


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
