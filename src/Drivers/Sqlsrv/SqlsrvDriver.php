<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Sqlsrv;

use DateInterval;
use DateTimeInterface;
use Nextras\Dbal\Connection;
use Nextras\Dbal\ConnectionException;
use Nextras\Dbal\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\ForeignKeyConstraintViolationException;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\NotNullConstraintViolationException;
use Nextras\Dbal\NotSupportedException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\SqlServerPlatform;
use Nextras\Dbal\QueryException;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\UniqueConstraintViolationException;
use Nextras\Dbal\Utils\DateTimeImmutable;


class SqlsrvDriver implements IDriver
{
	/** @var resource */
	private $connection;

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


	public function connect(array $params, callable $loggedQueryCallback): void
	{
		// see https://msdn.microsoft.com/en-us/library/ff628167.aspx
		static $knownConnectionOptions = [
			'App', 'ApplicationIntent', 'AttachDbFileName', 'CharacterSet',
			'ConnectionPooling', 'Encrypt', 'Falover_Partner', 'LoginTimeout',
			'MultipleActiveResultSet', 'MultiSubnetFailover', 'QuotedId',
			'ReturnDatesAsStrings', 'Scrollable', 'Server', 'TraceFile', 'TraceOn',
			'TransactionIsolation', 'TrustServerCertificate', 'WSID'
		];

		$this->loggedQueryCallback = $loggedQueryCallback;
		$connectionString = isset($params['port']) ? $params['host'] . ',' . $params['port'] : $params['host'];

		$connectionOptions = [];
		foreach ($params as $key => $value) {
			if ($key === 'username') {
				$connectionOptions['UID'] = $value;
			} elseif ($key === 'password') {
				$connectionOptions['PWD'] = $value ?: '';
			} elseif ($key === 'database') {
				$connectionOptions['Database'] = $value;
			} elseif ($key === 'connectionTz') {
				throw new NotSupportedException();
			} elseif (in_array($key, $knownConnectionOptions, TRUE)) {
				$connectionOptions[$key] = $value;
			}
		}

		if (isset($connectionInfo['ReturnDatesAsStrings'])) {
			throw new NotSupportedException("SqlsrvDriver does not allow to modify 'ReturnDatesAsStrings' parameter.");
		}
		$connectionOptions['ReturnDatesAsStrings'] = TRUE;

		$this->connection = sqlsrv_connect($connectionString, $connectionOptions);
		if (!$this->connection) {
			$this->throwErrors();
		}
	}


	public function disconnect(): void
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


	public function query(string $query): ?Result
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

		if (!$result = sqlsrv_query($this->connection, 'SELECT @@ROWCOUNT')) {
			$this->throwErrors();
		}

		if ($result = sqlsrv_fetch_array($result, SQLSRV_FETCH_NUMERIC)) {
			$this->affectedRows = $result[0];
		} else {
			$this->throwErrors();
		}

		return new Result(new SqlsrvResultAdapter($statement), $this);
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
		return new SqlServerPlatform($connection);
	}


	public function getServerVersion(): string
	{
		return sqlsrv_server_info($this->connection)['SQLServerVersion'];
	}


	public function ping(): bool
	{
		return sqlsrv_begin_transaction($this->connection);
	}


	public function setTransactionIsolationLevel(int $level): void
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


	public function beginTransaction(): void
	{
		if (!sqlsrv_begin_transaction($this->connection)) {
			$this->throwErrors();
		}
	}


	public function commitTransaction(): void
	{
		if (!sqlsrv_commit($this->connection)) {
			$this->throwErrors();
		}
	}


	public function rollbackTransaction(): void
	{
		if (!sqlsrv_rollback($this->connection)) {
			$this->throwErrors();
		}
	}


	public function createSavepoint(string $name): void
	{
		$this->loggedQuery('SAVE TRANSACTION ' . $this->convertIdentifierToSql($name));
	}


	public function releaseSavepoint(string $name): void
	{
		// transaction are released automatically
		// http://stackoverflow.com/questions/3101312/sql-server-2008-no-release-savepoint-for-current-transaction
	}


	public function rollbackSavepoint(string $name): void
	{
		$this->loggedQuery('ROLLBACK TRANSACTION ' . $this->convertIdentifierToSql($name));
	}


	public function convertToPhp(string $value, $nativeType)
	{
		if (
			$nativeType === SqlsrvResultTypes::TYPE_DECIMAL_MONEY_SMALLMONEY ||
			$nativeType === SqlsrvResultTypes::TYPE_NUMERIC
		) {
			return strpos($value, '.') === false ? (int) $value : (float) $value;

		} elseif ($nativeType === SqlsrvResultTypes::TYPE_DATETIMEOFFSET) {
			return new DateTimeImmutable($value);

		} else {
			throw new NotSupportedException("SqlsrvDriver does not support '{$nativeType}' type conversion.");
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
		// https://docs.microsoft.com/en-us/sql/t-sql/language-elements/like-transact-sql
		$value = strtr($value, [
			"'" => "''",
			'%' => '[%]',
			'_' => '[_]',
			'[' => '[[]',
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
		return "'" . $value->format('Y-m-d H:i:s P') . "'";
	}


	public function convertDateTimeSimpleToSql(DateTimeInterface $value): string
	{
		return "'" . $value->format('Y-m-d H:i:s') . "'";
	}


	public function convertDateIntervalToSql(DateInterval $value): string
	{
		throw new NotSupportedException();
	}


	public function convertBlobToSql(string $value): string
	{
		return '0x' . bin2hex($value);
	}


	public function modifyLimitQuery(string $query, ?int $limit, ?int $offset): string
	{
		$query .= ' OFFSET ' . (int) ($offset ?: 0) . ' ROWS';
		if ($limit !== NULL) {
			$query .= ' FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
		}
		return $query;
	}


	private function throwErrors($query = NULL)
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


	protected function createException($error, $errorNo, $sqlState, $query = NULL)
	{
		if (in_array($sqlState, ['HYT00', '08001', '28000'])) {
			return new ConnectionException($error, $errorNo, $sqlState, NULL, $query);

		} elseif (in_array($errorNo, [2627, 547], TRUE)) {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif (in_array($errorNo, [2601], TRUE)) {
			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif (in_array($errorNo, [515], TRUE)) {
			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif ($query !== NULL) {
			return new QueryException($error, $errorNo, $sqlState, NULL, $query);

		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}


	protected function loggedQuery(string $sql): ?Result
	{
		return ($this->loggedQueryCallback)($sql);
	}
}
