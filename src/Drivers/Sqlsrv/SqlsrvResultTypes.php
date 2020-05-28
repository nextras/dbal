<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Sqlsrv;


/**
 * @see https://docs.microsoft.com/en-us/sql/connect/php/sqlsrv-field-metadata
 */
final class SqlsrvResultTypes
{
	public const TYPE_INT = -5;
	public const TYPE_BIT = -7;
	public const TYPE_TIME = -154;
	public const TYPE_DATE = 91;
	public const TYPE_DATETIME_DATETIME2_SMALLDATETIME = 93;
	public const TYPE_DATETIMEOFFSET = -155;
	public const TYPE_NUMERIC = 2;
	public const TYPE_DECIMAL_MONEY_SMALLMONEY = 3;
}
