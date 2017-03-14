<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mssql;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Nextras\Dbal\Connection;
use Nextras\Dbal\ConnectionException;
use Nextras\Dbal\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\ForeignKeyConstraintViolationException;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\NotSupportedException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\MssqlSqlPlatform;
use Nextras\Dbal\QueryException;
use Nextras\Dbal\Result\Result;
use Tracy\Debugger;


class MssqlDriver implements IDriver
{
	/** @var resource */
	private $connection;

	/** @var DateTimeZone Timezone for columns without timezone handling (timestamp, datetime, time). */
	private $simpleStorageTz;

	/** @var DateTimeZone Timezone for database connection. */
	private $connectionTz;

	/** @var callable */
	private $loggedQueryCallback;

	/** @var int */
	private $affectedRows = 0;

	/** @var float */
	private $timeTaken = 0.0;


	public function __destruct()
	{
		$this->disconnect();
	}


	public function connect(array $params, callable $loggedQueryCallback)
	{
		/**
		 * @see https://msdn.microsoft.com/en-us/library/ff628167.aspx
		 */
		$knownConnectionOptions = [
			'App', 'ApplicationIntent', 'AttachDbFileName', 'CharacterSet',
			'ConnectionPooling', 'Encrypt', 'Falover_Partner', 'LoginTimeout',
			'MultipleActiveResultSet', 'MultiSubnetFailover', 'QuotedId',
			'ReturnDatesAsStrings', 'Scrollable', 'Server', 'TraceFile', 'TraceOn',
			'TransactionIsolation', 'TrustServerCertificate', 'WSID'
		];

		$connectionString = isset($params['port']) ? $params['host'] . ',' . $params['port'] : $params['host'];

		$connectionOptions = [];
		foreach ($params as $key => $value) {
			if ($key === 'user')
				$connectionOptions['UID'] = $value;
			elseif ($key === 'password')
				$connectionOptions['PWD'] = $value ?? '';
			elseif ($key === 'dbname')
				$connectionOptions['Database'] = $value;
			elseif ($key === 'simpleStorageTz')
				$this->simpleStorageTz = new DateTimeZone($value);
			elseif ($key === 'connectionTz')
				$this->connectionTz = new DateTimeZone($value);
			elseif (in_array($key, $knownConnectionOptions))
				$connectionOptions[$key] = $value;
		}

		if (isset($connectionInfo['ReturnDatesAsStrings']))
			throw new NotSupportedException("MssqlDriver does not allow to modify 'ReturnDatesAsStrings' parameter.");
		else
			$connectionOptions['ReturnDatesAsStrings'] = true;

		$this->connection = sqlsrv_connect($connectionString, $connectionOptions);
		if (!$this->connection) {
			$this->throwErrors();
		}
	}


	public function disconnect()
	{
		if ($this->connection) {
			sqlsrv_close($this->connection);
			$this->connection = NULL;
		}
	}


	public function isConnected(): bool
	{
		return $this->connection !== NULL;
	}


	public function getResourceHandle()
	{
		return $this->connection;
	}


	public function query(string $query)
	{
		/**
		 * @see https://msdn.microsoft.com/en-us/library/ee376927(SQL.90).aspx
		 */
		$statement = sqlsrv_prepare($this->connection, $query, [], ['Scrollable' => SQLSRV_CURSOR_STATIC]);

		if (!$statement) {
			$this->throwErrors();
		}

		$time = microtime(TRUE);
		$executed = sqlsrv_execute($statement);
		$this->timeTaken = microtime(TRUE) - $time;

		if (!$executed) {
			$this->throwErrors($query);
		}

		$this->setLastAffectedRows();

		return new Result(new MssqlResultAdapter($statement), $this);
	}


	private function setLastAffectedRows()
	{
		if (!$result = sqlsrv_query($this->connection, 'SELECT @@ROWCOUNT')) {
			$this->throwErrors();
		}

		if ($result = sqlsrv_fetch_array($result, SQLSRV_FETCH_NUMERIC)) {
			$this->affectedRows = $result[0];
		} else {
			$this->throwErrors();
		}
	}


	public function getLastInsertedId(string $sequenceName = NULL)
	{
		return $this->loggedQuery('SELECT @@IDENTITY')->fetchField();
	}


	public function getAffectedRows(): int
	{
		return $this->affectedRows;
	}


	public function getQueryElapsedTime(): float
	{
		return $this->timeTaken;
	}


	public function createPlatform(Connection $connection): IPlatform
	{
		return new MssqlSqlPlatform($connection);
	}


	public function getServerVersion(): string
	{
		return sqlsrv_server_info($this->connection)['SQLServerVersion'];
	}


	public function ping(): bool
	{
		return sqlsrv_begin_transaction($this->connection);
	}


	public function setTransactionIsolationLevel(int $level)
	{
		static $levels = [
			Connection::TRANSACTION_READ_UNCOMMITTED => 'READ UNCOMMITTED',
			Connection::TRANSACTION_READ_COMMITTED => 'READ COMMITTED',
			Connection::TRANSACTION_REPEATABLE_READ => 'REPEATABLE READ',
			Connection::TRANSACTION_SERIALIZABLE => 'SERIALIZABLE',
		];
		if (isset($levels[$level])) {
			throw new NotSupportedException("Unsupported transation level $level");
		}
		$this->loggedQuery("SET SESSION TRANSACTION ISOLATION LEVEL {$levels[$level]}");
	}


	public function beginTransaction()
	{
		if (!sqlsrv_begin_transaction($this->connection)) {
			$this->throwErrors();
		}
	}


	public function commitTransaction()
	{
		if (!sqlsrv_commit($this->connection)) {
			$this->throwErrors();
		}
	}


	public function rollbackTransaction()
	{
		if (!sqlsrv_rollback($this->connection)) {
			$this->throwErrors();
		}
	}


	public function createSavepoint(string $name)
	{
		$this->loggedQuery('SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function releaseSavepoint(string $name)
	{
		$this->loggedQuery('RELEASE SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function rollbackSavepoint(string $name)
	{
		$this->loggedQuery('ROLLBACK TO SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}

	public function convertToPhp(string $value, $nativeType)
	{
		if ($nativeType === SQLSRV_SQLTYPE_BIGINT) {
			return is_float($tmp = $value * 1) ? $value : $tmp;

		} elseif (
			$nativeType === MssqlResultAdapter::SQLTYPE_DECIMAL_MONEY_SMALLMONEY ||
			$nativeType === MssqlResultAdapter::SQLTYPE_NUMERIC
		) {
			$float = (float)$value;
			$string = (string)$float;
			return $value === $string ? $float : $value;

		} elseif (
			$nativeType === MssqlResultAdapter::SQLTYPE_DATE ||
			$nativeType === MssqlResultAdapter::SQLTYPE_DATETIME_DATETIME2_SMALLDATETIME ||
			$nativeType === MssqlResultAdapter::SQLTYPE_TIME
		) {
			return $value . ' ' . $this->simpleStorageTz->getName();

		} else {
			throw new NotSupportedException("MssqlDriver does not support '{$nativeType}' type conversion.");
		}
	}


	public function convertStringToSql(string $value): string
	{
		return "'" . str_replace("'", "''", $value) . "'";
	}


	public function convertJsonToSql($value): string
	{
		$encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
		if (json_last_error()) {
			throw new InvalidArgumentException('JSON Encode Error: ' . json_last_error_msg());
		}
		return $this->convertStringToSql($encoded);
	}


	public function convertLikeToSql(string $value, int $mode)
	{
		$value = strtr($value, [
			"'" => "''",
			'\\' => '\\\\',
			'%' => '\\%',
			'_' => '\\_',
		]);
		return ($mode <= 0 ? "'%" : "'") . $value . ($mode >= 0 ? "%'" : "'");
	}


	public function convertBoolToSql(bool $value): string
	{
		return $value ? '1' : '0';
	}


	public function convertIdentifierToSql(string $value): string
	{
		return str_replace('[*]', '*', '[' . str_replace([']', '.'], [']]', '].['], $value) . ']');
	}


	public function convertDateTimeToSql(DateTimeInterface $value): string
	{
		assert($value instanceof DateTime || $value instanceof DateTimeImmutable);
		if ($value->getTimezone()->getName() !== $this->connectionTz->getName()) {
			if ($value instanceof DateTimeImmutable) {
				$value = $value->setTimezone($this->connectionTz);
			} else {
				$value = clone $value;
				$value->setTimezone($this->connectionTz);
			}
		}
		return "'" . $value->format('Y-m-d\TH:i:s') . "'";
	}


	public function convertDateTimeSimpleToSql(DateTimeInterface $value): string
	{
		assert($value instanceof DateTime || $value instanceof DateTimeImmutable);
		if ($value->getTimezone()->getName() !== $this->simpleStorageTz->getName()) {
			if ($value instanceof DateTimeImmutable) {
				$value = $value->setTimezone($this->simpleStorageTz);
			} else {
				$value = clone $value;
				$value->setTimezone($this->simpleStorageTz);
			}
		}
		return "'" . $value->format('Y-m-d\TH:i:s') . "'";
	}


	public function convertDateIntervalToSql(DateInterval $value): string
	{
		return $value->format('P%yY%mM%dDT%hH%iM%sS');
	}


	public function convertBlobToSql(string $value): string
	{
		return '0x' . $value;
	}


	public function modifyLimitQuery(string $query, $limit, $offset): string
	{
		$query .= ' OFFSET ' . (int)($offset ?? 0) . ' ROWS';
		if ($limit !== NULL) {
			$query .= ' FETCH NEXT ' . (int)$limit . ' ROWS ONLY';
		}
		return $query;
	}

	private function throwErrors($query = null)
	{
		$errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
		$errors = array_unique($errors, SORT_REGULAR);
		$errors = array_reverse($errors);

		$exception = NULL;
		foreach ($errors as $error) {
			$exception = $this->createException(
				$error['message'],
				$error['code'],
				$error['SQLSTATE'],
				$query
			);
		}

		throw $exception;
	}


	/**
	 * This method is based on Doctrine\DBAL project.
	 * @link www.doctrine-project.org
	 */
	protected function createException($error, $errorNo, $sqlState, $query = NULL)
	{
		if (in_array($sqlState, ['HYT00', '08001', '28000', '42000'])) {
			return new ConnectionException($error, $errorNo, $sqlState, NULL, $query);

		} elseif (in_array($errorNo, [2627], TRUE)) {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

//		} elseif (in_array($errorNo, [1062, 1557, 1569, 1586], TRUE)) {
//			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);
//
//		} elseif (in_array($errorNo, [207], TRUE)) {
//			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif ($query !== NULL) {
			return new QueryException($error, $errorNo, $sqlState, NULL, $query);

		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}


	protected function loggedQuery(string $sql)
	{
		return ($this->loggedQueryCallback)($sql);
	}
}
