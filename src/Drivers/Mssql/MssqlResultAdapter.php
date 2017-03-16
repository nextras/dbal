<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mssql;

use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\InvalidStateException;


class MssqlResultAdapter implements IResultAdapter
{
	/** @var int */
	private $index;

	const
		SQLTYPE_TIME = -154,
		SQLTYPE_DATE = 91,
		SQLTYPE_DATETIME_DATETIME2_SMALLDATETIME = 93,
		SQLTYPE_DATETIMEOFFSET = -155,
		SQLTYPE_NUMERIC = 2,
		SQLTYPE_DECIMAL_MONEY_SMALLMONEY = 3;

	/**
	 * @var array
	 * @see https://docs.microsoft.com/en-us/sql/connect/php/sqlsrv-field-metadata
	 */
	protected static $types = [
		SQLSRV_SQLTYPE_BIGINT => self::TYPE_INT,
		SQLSRV_SQLTYPE_BIT => self::TYPE_BOOL,
		self::SQLTYPE_NUMERIC => self::TYPE_DRIVER_SPECIFIC,
		self::SQLTYPE_DECIMAL_MONEY_SMALLMONEY => self::TYPE_DRIVER_SPECIFIC,
		self::SQLTYPE_TIME => self::TYPE_DRIVER_SPECIFIC,
		self::SQLTYPE_DATE => self::TYPE_DRIVER_SPECIFIC || self::TYPE_DATETIME,
		self::SQLTYPE_DATETIME_DATETIME2_SMALLDATETIME => self::TYPE_DRIVER_SPECIFIC || self::TYPE_DATETIME,
		self::SQLTYPE_DATETIMEOFFSET => self::TYPE_DATETIME
	];

	/** @var resource */
	private $statement;


	public function __construct($statement)
	{
		$this->statement = $statement;

		if (PHP_INT_SIZE < 8) {
			self::$types['int8'] = self::TYPE_DRIVER_SPECIFIC;
		}
	}


	public function __destruct()
	{
		sqlsrv_free_stmt($this->statement);
	}


	public function seek(int $index)
	{
		if ($index !== 0 && sqlsrv_num_rows($this->statement) !== 0 && !sqlsrv_fetch($this->statement, SQLSRV_SCROLL_ABSOLUTE, $index)) {
			throw new InvalidStateException("Unable to seek in row set to {$index} index.");
		}
		$this->index = $index;
	}


	public function fetch()
	{
		if ($this->index !== null) {
			$index = $this->index;
			$this->index = null;
			return sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_ABSOLUTE, $index);
		}
		return sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_NEXT);
	}


	public function getTypes(): array
	{
		$types = [];
		$fields = sqlsrv_field_metadata($this->statement);
		foreach ($fields as $field) {
			$nativeType = $field['Type'];
			$types[$field['Name']] = [
				0 => isset(self::$types[$nativeType]) ? self::$types[$nativeType] : self::TYPE_AS_IS,
				1 => $nativeType
			];
		}
		return $types;
	}
}
