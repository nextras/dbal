<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Postgre;

use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\Exceptions\DbalException;


class PostgreResultAdapter implements IResultAdapter
{
	/** @var resource */
	private $result;


	public function __construct($result)
	{
		$this->result = $result;
	}


	public function __destruct()
	{
		pg_free_result($this->result);
	}


	public function seek($index)
	{
		if (pg_num_rows($this->result) !== 0 && !pg_result_seek($this->result, $index)) {
			throw new DbalException("Unable to seek in row set to {$index} index.");
		}
	}


	public function fetch()
	{
		return pg_fetch_array($this->result, NULL, PGSQL_ASSOC) ?: NULL;
	}


	public function getTypes()
	{
		$types = [];
		$count = pg_num_fields($this->result);

		for ($i = 0; $i < $count; $i++) {
			$nativeType = pg_field_type($this->result, $i);
			$types[pg_field_name($this->result, $i)] = [
				0 => self::TYPE_STRING,
				1 => $nativeType,
			];
		}

		return $types;
	}

}
