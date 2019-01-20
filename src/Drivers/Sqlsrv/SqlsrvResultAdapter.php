<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Sqlsrv;

use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\InvalidStateException;


class SqlsrvResultAdapter implements IResultAdapter
{
	/** @var array */
	protected static $types = [
		SqlsrvResultTypes::TYPE_INT                               => self::TYPE_INT,
		SqlsrvResultTypes::TYPE_BIT                               => self::TYPE_BOOL,
		SqlsrvResultTypes::TYPE_NUMERIC                           => self::TYPE_DRIVER_SPECIFIC,
		SqlsrvResultTypes::TYPE_DECIMAL_MONEY_SMALLMONEY          => self::TYPE_DRIVER_SPECIFIC,
		SqlsrvResultTypes::TYPE_TIME                              => self::TYPE_DATETIME,
		SqlsrvResultTypes::TYPE_DATE                              => self::TYPE_DATETIME,
		SqlsrvResultTypes::TYPE_DATETIME_DATETIME2_SMALLDATETIME  => self::TYPE_DATETIME,
		SqlsrvResultTypes::TYPE_DATETIMEOFFSET                    => self::TYPE_DRIVER_SPECIFIC,
	];

	/** @var int|null */
	private $index;

	/** @var resource */
	private $statement;


	public function __construct($statement)
	{
		$this->statement = $statement;
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
			$fetch = sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_ABSOLUTE, $index);
			if ($fetch === false) {
				return null;
			}
			return $fetch;
		}
		$fetch = sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_NEXT);
		if ($fetch === false) {
			return null;
		}
		return $fetch;
	}


	public function getTypes(): array
	{
		$types = [];
		$fields = sqlsrv_field_metadata($this->statement) ?: [];
		foreach ($fields as $field) {
			$nativeType = $field['Type'];
			$types[$field['Name']] = [
				0 => self::$types[$nativeType] ?? self::TYPE_AS_IS,
				1 => $nativeType,
			];
		}
		return $types;
	}


	public function getRowsCount(): int
	{
		$count = sqlsrv_num_rows($this->statement);
		return $count === false ? -1 : $count;
	}
}
