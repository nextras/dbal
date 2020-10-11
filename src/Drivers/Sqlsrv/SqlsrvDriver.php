<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Sqlsrv;


use DateInterval;
use DateTimeInterface;
use Exception;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\SqlServerPlatform;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Dbal\Utils\LoggerHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;


/**
 * Driver for php-sqlsrv ext available at github.com/microsoft/msphpsql.
 *
 * Supported configuration options:
 * - host - server name to connect;
 * - port - port to connect;
 * - database - db name to connect;
 * - username - username to connect;
 * - password - password to connect;
 * - other driver's config option:
 *    - App
 *    - ApplicationIntent
 *    - AttachDbFileName
 *    - CharacterSet
 *    - ConnectionPooling
 *    - Encrypt
 *    - Falover_Partner
 *    - LoginTimeout
 *    - MultipleActiveResultSet
 *    - MultiSubnetFailover
 *    - QuotedId
 *    - ReturnDatesAsStrings
 *    - Scrollable
 *    - TraceFile
 *    - TraceOn
 *    - TransactionIsolation
 *    - TrustServerCertificate
 *    - WSID
 */
class SqlsrvDriver implements IDriver
{
	use StrictObjectTrait;


	/** @var resource|null */
	private $connection;

	/** @var ILogger */
	private $logger;

	/** @var int */
	private $affectedRows = 0;

	/** @var float */
	private $timeTaken = 0.0;


	public function __destruct()
	{
		$this->disconnect();
	}


	public function connect(array $params, ILogger $logger): void
	{
		// see https://msdn.microsoft.com/en-us/library/ff628167.aspx
		static $knownConnectionOptions = [
			'App',
			'ApplicationIntent',
			'AttachDbFileName',
			'CharacterSet',
			'ConnectionPooling',
			'Encrypt',
			'Falover_Partner',
			'LoginTimeout',
			'MultipleActiveResultSet',
			'MultiSubnetFailover',
			'QuotedId',
			'ReturnDatesAsStrings',
			'Scrollable',
			'TraceFile',
			'TraceOn',
			'TransactionIsolation',
			'TrustServerCertificate',
			'WSID',
		];

		$this->logger = $logger;
		$connectionString = isset($params['port']) ? $params['host'] . ',' . $params['port'] : $params['host'];

		$connectionOptions = [];
		foreach ($params as $key => $value) {
			if ($key === 'username') {
				$connectionOptions['UID'] = $value;
			} elseif ($key === 'password') {
				$connectionOptions['PWD'] = $value ?? '';
			} elseif ($key === 'database') {
				$connectionOptions['Database'] = $value;
			} elseif ($key === 'connectionTz') {
				throw new NotSupportedException();
			} elseif (in_array($key, $knownConnectionOptions, true)) {
				$connectionOptions[$key] = $value;
			}
		}

		if (isset($connectionOptions['ReturnDatesAsStrings'])) {
			throw new NotSupportedException("SqlsrvDriver does not allow to modify 'ReturnDatesAsStrings' parameter.");
		}
		$connectionOptions['ReturnDatesAsStrings'] = true;

		$connectionResource = sqlsrv_connect($connectionString, $connectionOptions);
		if ($connectionResource === false) {
			$this->throwErrors();
		}
		$this->connection = $connectionResource;
	}


	public function disconnect(): void
	{
		if ($this->connection !== null) {
			sqlsrv_close($this->connection);
			$this->connection = null;
		}
	}


	public function isConnected(): bool
	{
		return $this->connection !== null;
	}


	public function getResourceHandle()
	{
		return $this->connection;
	}


	public function query(string $query): Result
	{
		assert($this->connection !== null);
		/** @see https://msdn.microsoft.com/en-us/library/ee376927(SQL.90).aspx */

		$time = microtime(true);
		$statement = sqlsrv_query($this->connection, $query, [], ['Scrollable' => SQLSRV_CURSOR_CLIENT_BUFFERED]);
		$this->timeTaken = microtime(true) - $time;

		if ($statement === false) {
			$this->throwErrors($query);
		}

		$affectedRowsStatement = sqlsrv_query($this->connection, 'SELECT @@ROWCOUNT');
		if ($affectedRowsStatement === false) {
			$this->throwErrors();
		}
		$affectedRowsResult = sqlsrv_fetch_array($affectedRowsStatement, SQLSRV_FETCH_NUMERIC);
		if (!is_array($affectedRowsResult)) {
			$this->throwErrors();
		}
		$this->affectedRows = $affectedRowsResult[0];

		return new Result(new SqlsrvResultAdapter($statement), $this);
	}


	public function getLastInsertedId(?string $sequenceName = null)
	{
		return $this->loggedQuery('SELECT SCOPE_IDENTITY()')->fetchField();
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
		assert($this->connection !== null);
		return sqlsrv_server_info($this->connection)['SQLServerVersion'];
	}


	public function ping(): bool
	{
		try {
			$this->query('SELECT 1');
			return true;
		} catch (DriverException $e) {
			return false;
		}
	}


	public function setTransactionIsolationLevel(int $level): void
	{
		static $levels = [
			Connection::TRANSACTION_READ_UNCOMMITTED => 'READ UNCOMMITTED',
			Connection::TRANSACTION_READ_COMMITTED => 'READ COMMITTED',
			Connection::TRANSACTION_REPEATABLE_READ => 'REPEATABLE READ',
			Connection::TRANSACTION_SERIALIZABLE => 'SERIALIZABLE',
		];
		if (!isset($levels[$level])) {
			throw new NotSupportedException("Unsupported transation level $level");
		}
		$this->loggedQuery("SET SESSION TRANSACTION ISOLATION LEVEL {$levels[$level]}");
	}


	public function beginTransaction(): void
	{
		assert($this->connection !== null);
		$time = microtime(true);
		$result = sqlsrv_begin_transaction($this->connection);
		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('BEGIN TRANSACTION', $timeTaken, null);
		if (!$result) {
			$this->throwErrors();
		}
	}


	public function commitTransaction(): void
	{
		assert($this->connection !== null);
		$time = microtime(true);
		$result = sqlsrv_commit($this->connection);
		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('COMMIT TRANSACTION', $timeTaken, null);
		if (!$result) {
			$this->throwErrors();
		}
	}


	public function rollbackTransaction(): void
	{
		assert($this->connection !== null);
		$time = microtime(true);
		$result = sqlsrv_rollback($this->connection);
		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('ROLLBACK TRANSACTION', $timeTaken, null);
		if (!$result) {
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
			$nativeType === SqlsrvResultTypes::TYPE_DECIMAL_MONEY_SMALLMONEY
			|| $nativeType === SqlsrvResultTypes::TYPE_NUMERIC
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
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new InvalidArgumentException('JSON Encode Error: ' . json_last_error_msg());
		}
		assert(is_string($encoded));
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
		return "CAST('" . $value->format('Y-m-d H:i:s.u P') . "' AS DATETIMEOFFSET)";
	}


	public function convertDateTimeSimpleToSql(DateTimeInterface $value): string
	{
		return "CAST('" . $value->format('Y-m-d H:i:s.u') . "' AS DATETIME2)";
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
		$query .= ' OFFSET ' . ($offset !== null ? $offset : 0) . ' ROWS';
		if ($limit !== null) {
			$query .= ' FETCH NEXT ' . $limit . ' ROWS ONLY';
		}
		return $query;
	}


	/**
	 * @phpstan-return never
	 */
	private function throwErrors(?string $query = null): void
	{
		$errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
		$errors = $errors === null ? [] : array_unique($errors, SORT_REGULAR);
		$error = array_shift($errors);
		if ($error === null) {
			return;
		}

		throw $this->createException(
			$error['message'],
			$error['code'],
			$error['SQLSTATE'],
			$query
		);
	}


	protected function createException(string $error, int $errorNo, string $sqlState, ?string $query = null): Exception
	{
		if (in_array($sqlState, ['HYT00', '08001', '28000'], true)) {
			return new ConnectionException($error, $errorNo, $sqlState);

		} elseif (in_array($errorNo, [547], true)) {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif (in_array($errorNo, [2601, 2627], true)) {
			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif (in_array($errorNo, [515], true)) {
			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($query !== null) {
			return new QueryException($error, $errorNo, $sqlState, null, $query);

		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}


	protected function loggedQuery(string $sql): Result
	{
		return LoggerHelper::loggedQuery($this, $this->logger, $sql);
	}
}
