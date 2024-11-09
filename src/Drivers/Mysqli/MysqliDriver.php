<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Mysqli;


use DateTimeZone;
use Exception;
use mysqli;
use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\UnknownMysqlTimezoneException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\MySqlPlatform;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\LoggerHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function assert;
use function mysqli_report;
use function str_replace;


/**
 * Driver for php_mysqli ext.
 *
 * Supported configuration options:
 * - host - server name to connect, if not defined, value is taken from ini's mysqli.default_host;
 * - port - port to connect, if not defined, value is taken from ini's mysqli.default_port, defaults to 3306;
 * - database - db name to connect;
 * - unix_socket - unix socket to connect, if not defined, value is taken from ini's mysqli.default_socket;
 * - flags - int of flags accepted by Mysqli::real_connect();
 * - username - username to connect;
 * - password - password to connect;
 * - sslKey - 0th argument of Mysqli::ssl_set();
 * - sslCert - 1st argument of Mysqli::ssl_set();
 * - sslCa - 2nd argument of Mysqli::ssl_set();
 * - sslCapath - 3rd argument of Mysqli::ssl_set();
 * - sslCipher - 4th argument of Mysqli::ssl_set();
 * - charset - connection charset; for MySQL >= 5.5.3 defaults to utf8mb4, defaults to utf8 otherwise;
 * - sqlMode - setup of SQL mode; possible values are:
 *    - "TRADITIONAL" - a default one;
 *    - "STRICT_TRANS_TABLES"
 *    - "ANSI"
 *    - and more - see MySQL docs.
 * - connectionTz - timezone for database connection; possible values are:
 *    - "auto"
 *    - "auto-offset"
 *    - specific +-00:00 timezone offset;
 */
class MysqliDriver implements IDriver
{
	use StrictObjectTrait;


	private ?\mysqli $connection = null;
	private ?\DateTimeZone $connectionTz = null;
	private ?ILogger $logger = null;
	private float $timeTaken = 0.0;
	private ?MysqliResultNormalizerFactory $resultNormalizerFactory = null;


	public function __destruct()
	{
		$this->disconnect();
	}


	public function connect(array $params, ILogger $logger): void
	{
		$this->logger = $logger;

		$host = $params['host'] ?? ini_get('mysqli.default_host');
		$port = (int) ($params['port'] ?? ini_get('mysqli.default_port'));
		$port = $port === 0 ? 3306 : $port;
		$dbname = $params['database'] ?? '';
		$socket = $params['unix_socket'] ?? ini_get('mysqli.default_socket');
		$flags = $params['flags'] ?? 0;

		mysqli_report(MYSQLI_REPORT_OFF); // Errors are checked explicitly.
		$this->connection = new mysqli();
		$this->resultNormalizerFactory = new MysqliResultNormalizerFactory($this);

		$this->setupSsl($params);

		assert($this->connection !== null);

		if (!@$this->connection->real_connect($host, $params['username'], (string) $params['password'], $dbname, $port, $socket, $flags)) {
			throw $this->createException(
				$this->connection->connect_error ?? $this->connection->error, // @phpstan-ignore-line
				$this->connection->connect_errno,
				'HY000',
			);
		}

		$this->processInitialSettings($params);
	}


	public function disconnect(): void
	{
		if ($this->connection !== null) {
			$this->connection->close();
			$this->connection = null;
		}
	}


	public function isConnected(): bool
	{
		return $this->connection !== null;
	}


	public function getResourceHandle(): ?mysqli
	{
		return $this->connection;
	}


	public function getConnectionTimeZone(): DateTimeZone
	{
		$this->checkConnection();
		assert($this->connectionTz !== null);
		return $this->connectionTz;
	}


	public function query(string $query): Result
	{
		$this->checkConnection();
		assert($this->connection !== null);
		assert($this->resultNormalizerFactory !== null);

		$time = microtime(true);
		$result = @$this->connection->query($query);
		$this->timeTaken = microtime(true) - $time;

		if ($result === false) {
			throw $this->createException(
				$this->connection->error,
				$this->connection->errno,
				$this->connection->sqlstate,
				$query,
			);
		}

		if ($result === true) {
			return new Result(new MysqliEmptyResultAdapter());
		}

		return new Result(new MysqliResultAdapter($result, $this->resultNormalizerFactory));
	}


	public function getLastInsertedId(string|Fqn|null $sequenceName = null): mixed
	{
		$this->checkConnection();
		assert($this->connection !== null);
		return $this->connection->insert_id;
	}


	public function getAffectedRows(): int
	{
		$this->checkConnection();
		assert($this->connection !== null);
		$affectedRows = $this->connection->affected_rows;
		if (is_string($affectedRows)) {
			// Integer overflow, read the real value via IConnection::getDriver()::getResourceHandle()->affected_rows
			return -2;
		}
		return $affectedRows;
	}


	public function getQueryElapsedTime(): float
	{
		return $this->timeTaken;
	}


	public function createPlatform(IConnection $connection): IPlatform
	{
		return new MySqlPlatform($connection);
	}


	public function getServerVersion(): string
	{
		$this->checkConnection();
		assert($this->connection !== null);
		$version = $this->connection->server_version;
		$majorVersion = floor($version / 10000);
		$minorVersion = floor(($version - $majorVersion * 10000) / 100);
		$patchVersion = floor($version - $majorVersion * 10000 - $minorVersion * 100);
		return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
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


	public function beginTransaction(): void
	{
		$this->checkConnection();
		$this->loggedQuery('START TRANSACTION');
	}


	public function commitTransaction(): void
	{
		$this->checkConnection();
		$this->loggedQuery('COMMIT');
	}


	public function rollbackTransaction(): void
	{
		$this->checkConnection();
		$this->loggedQuery('ROLLBACK');
	}


	public function createSavepoint(string|Fqn $name): void
	{
		$this->checkConnection();
		$this->loggedQuery('SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function releaseSavepoint(string|Fqn $name): void
	{
		$this->checkConnection();
		$this->loggedQuery('RELEASE SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function rollbackSavepoint(string|Fqn $name): void
	{
		$this->checkConnection();
		$this->loggedQuery('ROLLBACK TO SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	/**
	 * @param array<string, mixed> $params
	 */
	protected function setupSsl(array $params): void
	{
		$this->checkConnection();
		assert($this->connection !== null);

		if (
			!isset($params['sslKey'])
			&& !isset($params['sslCert'])
			&& !isset($params['sslCa'])
			&& !isset($params['sslCapath'])
			&& !isset($params['sslCipher'])
		) {
			return;
		}

		$this->connection->ssl_set(
			$params['sslKey'] ?? '',
			$params['sslCert'] ?? '',
			$params['sslCa'] ?? '',
			$params['sslCapath'] ?? '',
			$params['sslCipher'] ?? '',
		);
	}


	/**
	 * @param array<string, mixed> $params
	 */
	protected function processInitialSettings(array $params): void
	{
		$this->checkConnection();
		assert($this->connection !== null);

		if (isset($params['charset'])) {
			$charset = $params['charset'];
		} elseif (version_compare($this->getServerVersion(), '5.5.3', '>=')) {
			$charset = 'utf8mb4';
		} else {
			$charset = 'utf8';
		}

		$this->connection->set_charset($charset);

		if (!array_key_exists('sqlMode', $params)) {
			$params['sqlMode'] = 'TRADITIONAL';
		}
		if ($params['sqlMode'] !== null) {
			$this->loggedQuery('SET sql_mode = ' . $this->convertStringToSql($params['sqlMode']));
		}

		if (!isset($params['connectionTz']) || $params['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_NAME) {
			$params['connectionTz'] = date_default_timezone_get();
		} elseif ($params['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_OFFSET) {
			$params['connectionTz'] = date('P');
		}

		$this->connectionTz = new DateTimeZone($params['connectionTz']);
		$this->loggedQuery('SET time_zone = ' . $this->convertStringToSql($this->connectionTz->getName()));
	}


	public function convertStringToSql(string $value): string
	{
		$this->checkConnection();
		assert($this->connection !== null);
		return "'" . $this->connection->escape_string($value) . "'";
	}


	protected function convertIdentifierToSql(string|Fqn $identifier): string
	{
		$escaped = match (true) {
			$identifier instanceof Fqn => str_replace('`', '``', $identifier->schema) . '.'
				. str_replace('`', '``', $identifier->name),
			default => str_replace('`', '``', $identifier),
		};
		return '`' . $escaped . '`';
	}


	/**
	 * This method is based on Doctrine\DBAL project.
	 * @link www.doctrine-project.org
	 */
	protected function createException(string $error, int $errorNo, string $sqlState, ?string $query = null): Exception
	{
		if (in_array($errorNo, [1216, 1217, 1451, 1452, 1701], true)) {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, null, $query);
		} elseif (in_array($errorNo, [1062, 1557, 1569, 1586], true)) {
			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, null, $query);
		} elseif (in_array($errorNo, [1044, 1045, 1046, 1049, 1095, 1142, 1143, 1227, 1370, 2002, 2005, 2054], true)) {
			return new ConnectionException($error, $errorNo, $sqlState);
		} elseif (in_array($errorNo, [1048, 1121, 1138, 1171, 1252, 1263, 1566], true)) {
			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, null, $query);
		} elseif ($errorNo === 1298) {
			return new UnknownMysqlTimezoneException($error, $errorNo, $sqlState, null, $query);
		} elseif ($query !== null) {
			return new QueryException($error, $errorNo, $sqlState, null, $query);
		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}


	protected function loggedQuery(string $sql): Result
	{
		$this->checkConnection();
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
