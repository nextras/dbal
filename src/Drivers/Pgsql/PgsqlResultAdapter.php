<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Pgsql;

use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\InvalidStateException;


class PgsqlResultAdapter implements IResultAdapter
{
	/** @var resource */
	private $result;

	/**
	 * @var array
	 * @see http://www.postgresql.org/docs/9.4/static/datatype.html
	 */
	protected static $types = [
		'bool'        => self::TYPE_DRIVER_SPECIFIC,
		'bit'         => self::TYPE_DRIVER_SPECIFIC,
		'varbit'      => self::TYPE_DRIVER_SPECIFIC,
		'bytea'       => self::TYPE_DRIVER_SPECIFIC,
		'interval'    => self::TYPE_DRIVER_SPECIFIC,

		'int8'        => self::TYPE_INT,
		'int4'        => self::TYPE_INT,
		'int2'        => self::TYPE_INT,

		'numeric'     => self::TYPE_FLOAT,
		'float4'      => self::TYPE_FLOAT,
		'float8'      => self::TYPE_FLOAT,

		'time'        => self::TYPE_DATETIME,
		'date'        => self::TYPE_DATETIME,
		'timestamp'   => self::TYPE_DATETIME,
		'timetz'      => self::TYPE_DATETIME,
		'timestamptz' => self::TYPE_DATETIME,
	];


	public function __construct($result)
	{
		$this->result = $result;

		if (PHP_INT_SIZE < 8) {
			self::$types['int8'] = self::TYPE_DRIVER_SPECIFIC;
		}
	}


	public function __destruct()
	{
		pg_free_result($this->result);
	}


	public function seek(int $index)
	{
		if (pg_num_rows($this->result) !== 0 && !pg_result_seek($this->result, $index)) {
			throw new InvalidStateException("Unable to seek in row set to {$index} index.");
		}
	}


	public function fetch()
	{
		return pg_fetch_array($this->result, null, PGSQL_ASSOC) ?: null;
	}


	public function getTypes(): array
	{
		$types = [];
		$count = pg_num_fields($this->result);

		for ($i = 0; $i < $count; $i++) {
			$nativeType = pg_field_type($this->result, $i);
			$types[pg_field_name($this->result, $i)] = [
				0 => self::$types[$nativeType] ?? self::TYPE_AS_IS,
				1 => $nativeType,
			];
		}

		return $types;
	}


	public function getRowsCount(): int
	{
		return pg_num_rows($this->result);
	}
}
