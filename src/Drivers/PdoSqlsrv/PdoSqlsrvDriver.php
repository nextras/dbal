<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoSqlsrv;


use Exception;
use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\Pdo\PdoDriver;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\SqlServerPlatform;
use Nextras\Dbal\Result\IResultAdapter;
use PDO;
use PDOStatement;
use function in_array;


/**
 * Driver for php_pdo_sqlsrv ext available at PECL or github.com/microsoft/msphpsql.
 *
 * Supported configuration options:
 * - host - server name to connect;
 * - port - port to connect;
 * - database - db name to connect;
 * - username - username to connect;
 * - password - password to connect;
 * - other driver's config option:
 *    - App
 *    - ConnectionPooling
 *    - Encrypt
 *    - Failover_Partner
 *    - LoginTimeout
 *    - ReturnDatesAsStrings
 *    - TraceFile
 *    - TraceOn
 *    - TransactionIsolation
 *    - TrustServerCertificate
 *    - WSID
 */
class PdoSqlsrvDriver extends PdoDriver
{
	private ?PdoSqlsrvResultNormalizerFactory $resultNormalizerFactory = null;


	public function connect(array $params, ILogger $logger): void
	{
		// see https://msdn.microsoft.com/en-us/library/ff628167.aspx
		// see https://www.php.net/manual/en/ref.pdo-sqlsrv.connection.php
		static $knownConnectionOptions = [
			'App',
			'ConnectionPooling',
			'Encrypt',
			'Failover_Partner',
			'LoginTimeout',
			'ReturnDatesAsStrings',
			'TraceFile',
			'TraceOn',
			'TransactionIsolation',
			'TrustServerCertificate',
			'WSID',
		];

		$host = $params['host'] ?? '';
		$port = $params['port'] ?? 5432;
		$database = $params['database'] ?? '';
		$username = $params['username'] ?? '';
		$password = $params['password'] ?? '';

		$dsn = "sqlsrv:Server=$host,$port;Database=$database";
		foreach ($knownConnectionOptions as $knownOption) {
			if (isset($params[$knownOption])) {
				$dsn .= ";$knownOption={$params[$knownOption]}";
			}
		}

		$options = [
			PDO::SQLSRV_ATTR_DIRECT_QUERY => true,
		];
		$this->connectPdo($dsn, $username, $password, $options, $logger);
		$this->resultNormalizerFactory = new PdoSqlsrvResultNormalizerFactory();
	}


	public function createPlatform(IConnection $connection): IPlatform
	{
		return new SqlServerPlatform($connection);
	}


	public function getLastInsertedId(string|Fqn|null $sequenceName = null): mixed
	{
		$this->checkConnection();
		return $this->loggedQuery('SELECT SCOPE_IDENTITY()')->fetchField();
	}


	public function setTransactionIsolationLevel(int $level): void
	{
		$this->checkConnection();
		static $levels = [
			IConnection::TRANSACTION_READ_UNCOMMITTED => 'READ UNCOMMITTED',
			IConnection::TRANSACTION_READ_COMMITTED => 'READ COMMITTED',
			IConnection::TRANSACTION_REPEATABLE_READ => 'REPEATABLE READ',
			IConnection::TRANSACTION_SERIALIZABLE => 'SERIALIZABLE',
		];
		if (!isset($levels[$level])) {
			throw new NotSupportedException("Unsupported transaction level $level");
		}
		$this->loggedQuery("SET SESSION TRANSACTION ISOLATION LEVEL {$levels[$level]}");
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


	protected function createResultAdapter(PDOStatement $statement): IResultAdapter
	{
		assert($this->resultNormalizerFactory !== null);
		return (new PdoSqlsrvResultAdapter($statement, $this->resultNormalizerFactory))->toBuffered();
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


	protected function createException(string $error, int $errorNo, string $sqlState, ?string $query = null): Exception
	{
		if (
			in_array($sqlState, [
				'HYT00',
				'08001',
				'28000',
			], true)
			|| stripos($error, 'Cannot open database') !== false
		) {
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
}
