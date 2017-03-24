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
	const TYPE_INT = SQLSRV_SQLTYPE_BIGINT;
	const TYPE_BIT = SQLSRV_SQLTYPE_BIT;
	const TYPE_TIME = -154;
	const TYPE_DATE = 91;
	const TYPE_DATETIME_DATETIME2_SMALLDATETIME = 93;
	const TYPE_DATETIMEOFFSET = -155;
	const TYPE_NUMERIC = 2;
	const TYPE_DECIMAL_MONEY_SMALLMONEY = 3;
}
