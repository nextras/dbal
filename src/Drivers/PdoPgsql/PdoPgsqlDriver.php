<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoPgsql;


use DateInterval;
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
use Nextras\Dbal\Drivers\Pdo\PdoDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Dbal\Result\IResultAdapter;
use PDOStatement;
use function array_map;
use function date;
use function date_default_timezone_get;
use function implode;
use function stream_get_contents;


/**
 * Driver for php_pdo_pgsql ext.
 *
 */
class PdoPgsqlDriver extends PdoDriver
{
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
		$this->processInitialSettings($params);
	}


	public function createPlatform(Connection $connection): IPlatform
	{
		return new PostgreSqlPlatform($connection);
	}


	public function getLastInsertedId(?string $sequenceName = null)
	{
		if ($sequenceName === null) {
			throw new InvalidArgumentException('PgsqlDriver requires to pass sequence name for getLastInsertedId() method.');
		}

		assert($this->connection !== null);
		$sql = 'SELECT CURRVAL(' . $this->convertStringToSql($sequenceName) . ')';
		return $this->loggedQuery($sql)->fetchField();
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
			throw new NotSupportedException("Unsupported transaction level $level");
		}
		$this->loggedQuery("SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL {$levels[$level]}");
	}


	public function convertToPhp($value, $nativeType)
	{
		if ($nativeType === 'int8') { // called only on 32bit
			return is_float($tmp = $value * 1) ? $value : $tmp; // @phpstan-ignore-line

		} elseif ($nativeType === 'interval') {
			return DateInterval::createFromDateString($value);

		} elseif ($nativeType === 'bit' || $nativeType === 'varbit') {
			return bindec($value);

		} elseif ($nativeType === 'bytea') {
			if (!is_resource($value)) {
				throw new InvalidStateException();
			}
			return stream_get_contents($value);

		} else {
			return parent::convertToPhp($value, $nativeType);
		}
	}


	protected function createResultAdapter(PDOStatement $statement): IResultAdapter
	{
		return (new PdoPgsqlResultAdapter($statement))->toBuffered();
	}


	protected function convertIdentifierToSql(string $identifier): string
	{
		return '"' . str_replace(['"', '.'], ['""', '"."'], $identifier) . '"';
	}


	/**
	 * This method is based on Doctrine\DBAL project.
	 * @link www.doctrine-project.org
	 */
	protected function createException(string $error, int $errorNo, ?string $sqlState, ?string $query = null): Exception
	{
		// see codes at http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
		if ($sqlState === '0A000' && strpos($error, 'truncate') !== false) {
			// Foreign key constraint violations during a TRUNCATE operation
			// are considered "feature not supported" in PostgreSQL.
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($sqlState === '23502') {
			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($sqlState === '23503') {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($sqlState === '23505') {
			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, null, $query);

		} elseif ($sqlState === null && stripos($error, 'pg_connect()') !== false) {
			return new ConnectionException($error, $errorNo, $sqlState);

		} elseif ($query !== null) {
			return new QueryException($error, $errorNo, (string) $sqlState, null, $query);

		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}


	/**
	 * @phpstan-param array<string, mixed> $params
	 */
	protected function processInitialSettings(array $params): void
	{
		if (!isset($params['connectionTz']) || $params['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_NAME) {
			$params['connectionTz'] = date_default_timezone_get();
		} elseif ($params['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_OFFSET) {
			$params['connectionTz'] = date('P');
		}

		$this->connectionTz = new DateTimeZone($params['connectionTz']);
		if (strpos($this->connectionTz->getName(), ':') !== false) {
			$this->loggedQuery('SET TIME ZONE INTERVAL ' . $this->convertStringToSql($this->connectionTz->getName()) . ' HOUR TO MINUTE');
		} else {
			$this->loggedQuery('SET TIME ZONE ' . $this->convertStringToSql($this->connectionTz->getName()));
		}

		if (isset($params['searchPath'])) {
			$schemas = array_map(function ($part): string {
				return $this->convertIdentifierToSql($part);
			}, (array) $params['searchPath']);
			$this->loggedQuery('SET search_path TO ' . implode(', ', $schemas));
		}
	}
}
