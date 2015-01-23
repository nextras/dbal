<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mysql;

use mysqli_result;
use Nextras\Dbal\Drivers\IRowsetAdapter;
use Nextras\Dbal\Exceptions\DbalException;


class MysqlRowsetAdapter implements IRowsetAdapter
{
	/** @var array */
	protected static $types = [
		MYSQLI_TYPE_TIME        => self::TYPE_DRIVER_SPECIFIC,

		MYSQLI_TYPE_BLOB        => self::TYPE_STRING,
		MYSQLI_TYPE_TINY_BLOB   => self::TYPE_STRING,
		MYSQLI_TYPE_MEDIUM_BLOB => self::TYPE_STRING,
		MYSQLI_TYPE_LONG_BLOB   => self::TYPE_STRING,

		MYSQLI_TYPE_CHAR        => self::TYPE_STRING,
		MYSQLI_TYPE_ENUM        => self::TYPE_STRING,
		MYSQLI_TYPE_GEOMETRY    => self::TYPE_STRING,
		MYSQLI_TYPE_NEWDATE     => self::TYPE_STRING,
		MYSQLI_TYPE_SET         => self::TYPE_STRING,
		MYSQLI_TYPE_STRING      => self::TYPE_STRING,
		MYSQLI_TYPE_VAR_STRING  => self::TYPE_STRING,

		MYSQLI_TYPE_BIT         => self::TYPE_INT, // returned as int
		MYSQLI_TYPE_INT24       => self::TYPE_INT,
		MYSQLI_TYPE_INTERVAL    => self::TYPE_INT,
		MYSQLI_TYPE_TINY        => self::TYPE_INT,
		MYSQLI_TYPE_SHORT       => self::TYPE_INT,
		MYSQLI_TYPE_LONG        => self::TYPE_INT,
		MYSQLI_TYPE_LONGLONG    => self::TYPE_INT,
		MYSQLI_TYPE_YEAR        => self::TYPE_INT,

		MYSQLI_TYPE_DECIMAL     => self::TYPE_FLOAT,
		MYSQLI_TYPE_NEWDECIMAL  => self::TYPE_FLOAT,
		MYSQLI_TYPE_DOUBLE      => self::TYPE_FLOAT,
		MYSQLI_TYPE_FLOAT       => self::TYPE_FLOAT,

		MYSQLI_TYPE_DATE        => self::TYPE_DATETIME,
		MYSQLI_TYPE_DATETIME    => self::TYPE_DATETIME,
		MYSQLI_TYPE_TIMESTAMP   => self::TYPE_DATETIME,
	];

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
		if (!$this->result->data_seek($index)) {
			throw new DbalException("Unable to seek in row set to {$index} index.");
		}
	}


	public function fetch()
	{
		return $this->result->fetch_assoc();
	}


	public function getTypes()
	{
		$types = [];
		$count = $this->result->field_count;

		for ($i = 0; $i < $count; $i++) {
			$field = (array) $this->result->fetch_field_direct($i);
			$types[$field['name']] = [
				0 => isset(self::$types[$field['type']]) ? self::$types[$field['type']] : self::TYPE_STRING,
				1 => $field['type'],
			];
		}

		return $types;
	}

}
