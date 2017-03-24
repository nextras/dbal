<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Sqlsrv;


/**
 * @see https://docs.microsoft.com/en-us/sql/connect/php/sqlsrv-field-metadata
 */
final class SqlsrvTypes
{
	public const TYPE_INT = SQLSRV_SQLTYPE_BIGINT;
	public const TYPE_BIT = SQLSRV_SQLTYPE_BIT;
	public const TYPE_TIME = -154;
	public const TYPE_DATE = 91;
	public const TYPE_DATETIME_DATETIME2_SMALLDATETIME = 93;
	public const TYPE_DATETIMEOFFSET = -155;
	public const TYPE_NUMERIC = 2;
	public const TYPE_DECIMAL_MONEY_SMALLMONEY = 3;
}
