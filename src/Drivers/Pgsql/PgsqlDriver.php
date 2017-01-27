<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Pgsql;

use DateInterval;
use DateTimeZone;
use Nextras\Dbal\Connection;
use Nextras\Dbal\ConnectionException;
use Nextras\Dbal\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\ForeignKeyConstraintViolationException;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\NotNullConstraintViolationException;
use Nextras\Dbal\NotSupportedException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Dbal\QueryException;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\UniqueConstraintViolationException;


class PgsqlDriver implements IDriver
{
	/** @var resource */
	private $connection;

	/** @var DateTimeZone Timezone for columns without timezone handling (timestamp, datetime, time). */
	private $simpleStorageTz;

	/** @var DateTimeZone Timezone for database connection. */
	private $connectionTz;

	/** @var int */
	private $affectedRows = 0;


	public function __destruct()
	{
		$this->disconnect();
	}


	public function connect(array $params)
	{
		static $knownKeys = [
			'host', 'hostaddr', 'port', 'dbname', 'user', 'password',
			'connect_timeout', 'options', 'sslmode', 'service',
		];

		$connectionString = '';
		foreach ($knownKeys as $key) {
			if (isset($params[$key])) {
				$connectionString .= $key . '=' . $params[$key] . ' ';
			}
		}

		set_error_handler(function($code, $message) {
			restore_error_handler();
			throw $this->createException($message, $code, NULL);
		}, E_ALL);

		$this->connection = pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);

		restore_error_handler();

		$this->simpleStorageTz = new DateTimeZone($params['simpleStorageTz']);
		$this->connectionTz = new DateTimeZone($params['connectionTz']);
		$this->query('SET TIME ZONE ' . pg_escape_literal($this->connectionTz->getName()));
	}


	public function disconnect()
	{
		if ($this->connection) {
			pg_close($this->connection);
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
		if (!pg_send_query($this->connection, $query)) {
			throw $this->createException(pg_last_error($this->connection), 0, NULL);
		}

		$time = microtime(TRUE);
		$resource = pg_get_result($this->connection);
		$time = microtime(TRUE) - $time;

		if ($resource === FALSE) {
			throw $this->createException(pg_last_error($this->connection), 0, NULL);
		}

		$state = pg_result_error_field($resource, PGSQL_DIAG_SQLSTATE);
		if ($state !== NULL) {
			throw $this->createException(pg_result_error($resource), 0, $state, $query);
		}

		$this->affectedRows = pg_affected_rows($resource);
		return new Result(new PgsqlResultAdapter($resource), $this, $time);
	}


	public function getLastInsertedId(string $sequenceName = NULL)
	{
		if (empty($sequenceName)) {
			throw new InvalidArgumentException('PgsqlDriver require to pass sequence name for getLastInsertedId() method.');
		}
		$sql = 'SELECT CURRVAL(' . pg_escape_literal($this->connection, $sequenceName) . ')';
		return $this->query($sql)->fetchField();
	}


	public function getAffectedRows(): int
	{
		return $this->affectedRows;
	}


	public function createPlatform(Connection $connection): IPlatform
	{
		return new PostgreSqlPlatform($connection);
	}


	public function getServerVersion(): string
	{
		return pg_version($this->connection)['server'];
	}


	public function ping(): bool
	{
		return pg_ping($this->connection);
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
		$this->query("SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL {$levels[$level]}");
	}


	public function beginTransaction()
	{
		$this->query('START TRANSACTION');
	}


	public function commitTransaction()
	{
		$this->query('COMMIT');
	}


	public function rollbackTransaction()
	{
		$this->query('ROLLBACK');
	}


	public function createSavepoint(string $name)
	{
		$this->query('SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function releaseSavepoint(string $name)
	{
		$this->query('RELEASE SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function rollbackSavepoint(string $name)
	{
		$this->query('ROLLBACK TO SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function convertToPhp(string $value, $nativeType)
	{
		static $trues = ['true', 't', 'yes', 'y', 'on', '1'];

		if ($nativeType === 'bool') {
			return in_array(strtolower($value), $trues, TRUE);

		} elseif ($nativeType === 'time' || $nativeType === 'date' || $nativeType === 'timestamp') {
			return $value . ' ' . $this->simpleStorageTz->getName();

		} elseif ($nativeType === 'int8') {
			// called only on 32bit
			return is_float($tmp = $value * 1) ? $value : $tmp;

		} elseif ($nativeType === 'interval') {
			return DateInterval::createFromDateString($value);

		} elseif ($nativeType === 'bit' || $nativeType === 'varbit') {
			return bindec($value);

		} elseif ($nativeType === 'bytea') {
			return pg_unescape_bytea($value);

		} else {
			throw new NotSupportedException("PgsqlDriver does not support '{$nativeType}' type conversion.");
		}
	}


	public function convertStringToSql(string $value): string
	{
		return pg_escape_literal($this->connection, $value);
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
		return $value ? 'TRUE' : 'FALSE';
	}


	public function convertIdentifierToSql(string $value): string
	{
		$parts = explode('.', $value);
		foreach ($parts as &$part) {
			if ($part !== '*') {
				$part = pg_escape_identifier($this->connection, $part);
			}
		}
		return implode('.', $parts);
	}


	public function convertDateTimeToSql(\DateTimeInterface $value): string
	{
		assert($value instanceof \DateTime || $value instanceof \DateTimeImmutable);
		if ($value->getTimezone()->getName() !== $this->connectionTz->getName()) {
			if ($value instanceof \DateTimeImmutable) {
				$value = $value->setTimezone($this->connectionTz);
			} else {
				$value = clone $value;
				$value->setTimezone($this->connectionTz);
			}
		}
		return "'" . $value->format('Y-m-d H:i:s') . "'::timestamptz";
	}


	public function convertDateTimeSimpleToSql(\DateTimeInterface $value): string
	{
		assert($value instanceof \DateTime || $value instanceof \DateTimeImmutable);
		if ($value->getTimezone()->getName() !== $this->simpleStorageTz->getName()) {
			if ($value instanceof \DateTimeImmutable) {
				$value = $value->setTimezone($this->simpleStorageTz);
			} else {
				$value = clone $value;
				$value->setTimezone($this->simpleStorageTz);
			}
		}
		return "'" . $value->format('Y-m-d H:i:s') . "'::timestamp";
	}


	public function convertDateIntervalToSql(\DateInterval $value): string
	{
		return $value->format('P%yY%mM%dDT%hH%iM%sS');
	}


	public function convertBlobToSql(string $value): string
	{
		return "'" . pg_escape_bytea($this->connection, $value) . "'";
	}


	public function modifyLimitQuery(string $query, $limit, $offset): string
	{
		if ($limit !== NULL) {
			$query .= ' LIMIT ' . (int) $limit;
		}
		if ($offset !== NULL) {
			$query .= ' OFFSET ' . (int) $offset;
		}
		return $query;
	}


	/**
	 * This method is based on Doctrine\DBAL project.
	 * @link www.doctrine-project.org
	 */
	protected function createException($error, $errorNo, $sqlState, $query = NULL)
	{
		// see codes at http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
		if ($sqlState === '0A000' && strpos($error, 'truncate') !== FALSE) {
			// Foreign key constraint violations during a TRUNCATE operation
			// are considered "feature not supported" in PostgreSQL.
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif ($sqlState === '23502') {
			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif ($sqlState === '23503') {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif ($sqlState === '23505') {
			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif ($sqlState === NULL && stripos($error, 'pg_connect()') !== FALSE) {
			return new ConnectionException($error, $errorNo, $sqlState);

		} elseif ($query !== NULL) {
			return new QueryException($error, $errorNo, $sqlState, NULL, $query);

		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}
}
