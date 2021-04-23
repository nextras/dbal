<?php declare(strict_types = 1);

namespace Nextras\Dbal\Platforms\Data;


use Nextras\Dbal\Utils\StrictObjectTrait;


class Table
{
	use StrictObjectTrait;


	/** @var string */
	public $name;

	/** @var string */
	public $schema;

	/** @var bool */
	public $isView;


	public function getNameFqn(): string
	{
		if ($this->schema === '') {
			return $this->name;
		} else {
			return "$this->schema.$this->name";
		}
	}
}
