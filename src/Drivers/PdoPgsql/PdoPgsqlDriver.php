<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoPgsql;


use DateTimeZone;
use Exception;
use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\Pdo\PdoDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Dbal\Result\IResultAdapter;
use PDOStatement;
use function array_map;
use function date;
use function date_default_timezone_get;
use function implode;


/**
 * Driver for php_pdo_pgsql ext.
 *
 * Supported configuration options:
 * - host - server name to connect;
 * - port - port to connect;
 * - database - db name to connect;
 * - options - options for PDO();
 * - username - username to connect;
 * - password - password to connect;
 * - searchPath - default search path for connection;
 * - connectionTz - timezone for database connection; possible values are:
 *    - "auto"
 *    - "auto-offset"
 *    - specific +-00:00 timezone offset;
 */
class PdoPgsqlDriver extends PdoDriver
{
	private ?PdoPgsqlResultNormalizerFactory $resultNormalizerFactory = null;


	public function connect(array $params, ILogger $logger): void
	{
		$host = $params['host'] ?? '';
		$port = $params['port'] ?? 5432;
		$username = $params['username'] ?? '';
		$password = $params['password'] ?? '';
		$database = $params['database'] ?? '';
		$options = (array) ($params['options'] ?? []);

		$dsn = "pgsql:host=$host;port=$port;dbname=$database";

		$this->connectPdo($dsn, $username, $password, $options, $logger);
		$this->resultNormalizerFactory = new PdoPgsqlResultNormalizerFactory();

		$this->processInitialSettings($params);
	}


	public function createPlatform(IConnection $connection): IPlatform
	{
		return new PostgreSqlPlatform($connection);
	}


	public function getLastInsertedId(string|Fqn|null $sequenceName = null): mixed
	{
		if ($sequenceName === null) {
			throw new InvalidArgumentException('PgsqlDriver requires passing a sequence name for getLastInsertedId() method.');
		}

		$this->checkConnection();
		assert($this->connection !== null);

		if ($sequenceName instanceof Fqn) {
			$sequenceName = $this->convertIdentifierToSql($sequenceName);
			$sql = 'SELECT CURRVAL(\'' . $sequenceName . '\')';
		} else {
			$sequenceName = $this->convertStringToSql($sequenceName);
			$sql = "SELECT CURRVAL($sequenceName)";
		}
		return $this->loggedQuery($sql)->fetchField();
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
		$this->loggedQuery("SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL {$levels[$level]}");
	}


	protected function createResultAdapter(PDOStatement $statement): IResultAdapter
	{
		assert($this->resultNormalizerFactory !== null);
		return (new PdoPgsqlResultAdapter($statement, $this->resultNormalizerFactory))->toBuffered();
	}


	protected function convertIdentifierToSql(string|Fqn $identifier): string
	{
		return match (true) {
			$identifier instanceof Fqn => '"' . str_replace('"', '""', $identifier->schema) . '"."'
				. str_replace('"', '""', $identifier->name) . '"',
			default => '"' . str_replace('"', '""', $identifier) . '"',
		};
	}


	/**
	 * This method is based on Doctrine\DBAL project.
	 * @link www.doctrine-project.org
	 */
	protected function createException(string $error, int $errorNo, string $sqlState, ?string $query = null): Exception
	{
		// see codes at http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
		if ($sqlState === '0A000' && str_contains($error, 'truncate')) {
			// Foreign key constraint violations during a TRUNCATE operation
			// are considered "feature not supported" in PostgreSQL.
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($sqlState === '23502') {
			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($sqlState === '23503') {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($sqlState === '23505') {
			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($sqlState === '08006') {
			return new ConnectionException($error, $errorNo, $sqlState);

		} elseif ($query !== null) {
			return new QueryException($error, $errorNo, $sqlState, null, $query);

		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}


	/**
	 * @param array<string, mixed> $params
	 */
	protected function processInitialSettings(array $params): void
	{
		if (!isset($params['connectionTz']) || $params['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_NAME) {
			$params['connectionTz'] = date_default_timezone_get();
		} elseif ($params['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_OFFSET) {
			$params['connectionTz'] = date('P');
		}

		$this->loggedQuery("SET intervalstyle = 'iso_8601'");

		$this->connectionTz = new DateTimeZone($params['connectionTz']);
		if (str_contains($this->connectionTz->getName(), ':')) {
			$this->loggedQuery('SET TIME ZONE INTERVAL ' . $this->convertStringToSql($this->connectionTz->getName()) . ' HOUR TO MINUTE');
		} else {
			$this->loggedQuery('SET TIME ZONE ' . $this->convertStringToSql($this->connectionTz->getName()));
		}

		if (isset($params['searchPath'])) {
			$schemas = array_map(
				fn($part): string => $this->convertIdentifierToSql($part),
				(array) $params['searchPath'],
			);
			$this->loggedQuery('SET search_path TO ' . implode(', ', $schemas));
		}
	}
}
