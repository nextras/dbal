<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Mysqli;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Utils\StrictObjectTrait;


class MysqliEmptyResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	public function toBuffered(): IResultAdapter
	{
		return $this;
	}


	public function toUnbuffered(): IResultAdapter
	{
		return $this;
	}


	public function seek(int $index): void
	{
		throw new InvalidArgumentException("Unable to seek in row set to {$index} index.");
	}


	public function fetch(): ?array
	{
		return null;
	}


	public function getRowsCount(): int
	{
		return 0;
	}


	public function getTypes(): array
	{
		return [];
	}


	public function getNormalizers(): array
	{
		return [];
	}
}
