<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mysql;

use mysqli_result;
use Nextras\Dbal\Drivers\IRowsetAdapter;


class MysqlRowsetAdapter implements IRowsetAdapter
{
	/** @var mysqli_result */
	private $result;


	public function __construct(mysqli_result $result)
	{
		$this->result = $result;
	}


	public function __destruct()
	{
		$this->result->free();
	}


	public function seek($index)
	{
		return $this->result->data_seek($index);
	}


	public function fetch()
	{
		return $this->result->fetch_assoc();
	}

}
