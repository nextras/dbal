<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Sqlsrv;


use DateTimeZone;
use Exception;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\SqlServerPlatform;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\LoggerHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;


/**
 * Driver for php-sqlsrv ext available at PECL or github.com/microsoft/msphpsql.
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
 *    - Failover_Partner
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
	private ?ILogger $logger = null;
	private int $affectedRows = 0;
	private float $timeTaken = 0.0;
	private ?SqlsrvResultNormalizationFactory $resultNormalizationFactory = null;


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
			'Failover_Partner',
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
		$this->resultNormalizationFactory = new SqlsrvResultNormalizationFactory();
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


	public function getResourceHandle(): mixed
	{
		return $this->connection;
	}


	public function getConnectionTimeZone(): DateTimeZone
	{
		throw new NotSupportedException();
	}


	public function query(string $query): Result
	{
		$this->checkConnection();
		assert($this->connection !== null);
		assert($this->resultNormalizationFactory !== null);

		// see https://msdn.microsoft.com/en-us/library/ee376927(SQL.90).aspx
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

		return new Result(new SqlsrvResultAdapter($statement, $this->resultNormalizationFactory));
	}


	public function getLastInsertedId(string|Fqn|null $sequenceName = null): mixed
	{
		$this->checkConnection();
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


	public function createPlatform(IConnection $connection): IPlatform
	{
		return new SqlServerPlatform($connection);
	}


	public function getServerVersion(): string
	{
		$this->checkConnection();
		assert($this->connection !== null);
		return sqlsrv_server_info($this->connection)['SQLServerVersion'];
	}


	public function ping(): bool
	{
		$this->checkConnection();
		try {
			$this->query('SELECT 1');
			return true;
		} catch (DriverException) {
			return false;
		}
	}


	public function setTransactionIsolationLevel(int $level): void
	{
		$this->checkConnection();
		static $levels = [
			Connection::TRANSACTION_READ_UNCOMMITTED => 'READ UNCOMMITTED',
			Connection::TRANSACTION_READ_COMMITTED => 'READ COMMITTED',
			Connection::TRANSACTION_REPEATABLE_READ => 'REPEATABLE READ',
			Connection::TRANSACTION_SERIALIZABLE => 'SERIALIZABLE',
		];
		if (!isset($levels[$level])) {
			throw new NotSupportedException("Unsupported transaction level $level");
		}
		$this->loggedQuery("SET SESSION TRANSACTION ISOLATION LEVEL {$levels[$level]}");
	}


	public function beginTransaction(): void
	{
		$this->checkConnection();
		assert($this->connection !== null);
		assert($this->logger !== null);

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
		$this->checkConnection();
		assert($this->connection !== null);
		assert($this->logger !== null);

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
		$this->checkConnection();
		assert($this->connection !== null);
		assert($this->logger !== null);

		$time = microtime(true);
		$result = sqlsrv_rollback($this->connection);
		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('ROLLBACK TRANSACTION', $timeTaken, null);
		if (!$result) {
			$this->throwErrors();
		}
	}


	public function createSavepoint(string|Fqn $name): void
	{
		$this->checkConnection();
		$this->loggedQuery('SAVE TRANSACTION ' . $this->convertIdentifierToSql($name));
	}


	public function releaseSavepoint(string|Fqn $name): void
	{
		// transaction are released automatically
		// http://stackoverflow.com/questions/3101312/sql-server-2008-no-release-savepoint-for-current-transaction
	}


	public function rollbackSavepoint(string|Fqn $name): void
	{
		$this->checkConnection();
		$this->loggedQuery('ROLLBACK TRANSACTION ' . $this->convertIdentifierToSql($name));
	}


	public function convertStringToSql(string $value): string
	{
		return "'" . str_replace("'", "''", $value) . "'";
	}


	protected function convertIdentifierToSql(string|Fqn $identifier): string
	{
		$escaped = match (true) {
			$identifier instanceof Fqn => str_replace(']', ']]', $identifier->schema) . '.'
				. str_replace(']', ']]', $identifier->name),
			default => str_replace(']', ']]', $identifier),
		};
		return '[' . $escaped . ']';
	}


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
			$query,
		);
	}


	protected function createException(string $error, int $errorNo, string $sqlState, ?string $query = null): Exception
	{
		if (in_array($sqlState, ['HYT00', '08001', '28000'], true) || $errorNo === 4060) {
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
		assert($this->logger !== null);
		return LoggerHelper::loggedQuery($this, $this->logger, $sql);
	}


	protected function checkConnection(): void
	{
		if ($this->connection === null) {
			throw new InvalidStateException("Driver is not connected to database.");
		}
	}
}
