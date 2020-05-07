<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mysqli;

use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Utils\StrictObjectTrait;


class MysqliEmptyResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	public function seek(int $index): void
	{
		throw new InvalidArgumentException("Unable to seek in row set to {$index} index.");
	}


	public function fetch(): ?array
	{
		return null;
	}


	public function getTypes(): array
	{
		return [];
	}


	public function getRowsCount(): int
	{
		return 0;
	}
}
