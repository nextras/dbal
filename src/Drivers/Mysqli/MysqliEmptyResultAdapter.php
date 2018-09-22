<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mysqli;

use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\InvalidStateException;


class MysqliEmptyResultAdapter implements IResultAdapter
{
	public function seek(int $index)
	{
		throw new InvalidStateException("Unable to seek in row set to {$index} index.");
	}


	public function fetch()
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
