<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Postgre;

use DateInterval;
use DateTimeZone;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\DriverException;
use Nextras\Dbal\Exceptions;
use Nextras\Dbal\Platforms\PostgrePlatform;
use Nextras\Dbal\Result\Result;


class PostgreDriver implements IDriver
{
	/** @var resource */
	private $connection;

	/** @var DateTimeZone Timezone for columns without timezone handling (timestamp, datetime, time). */
	private $simpleStorageTz;

	/** @var DateTimeZone Timezone for database connection. */
	private $connectionTz;


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

		if (!isset($params['user']) && isset($params['username'])) {
			$params['user'] = $params['username'];
		}
		if (!isset($params['dbname']) && isset($params['database'])) {
			$params['dbname'] = $params['database'];
		}

		$connectionString = '';
		foreach ($knownKeys as $key) {
			if (isset($params[$key])) {
				$connectionString .= $key . '=' . $params[$key] . ' ';
			}
		}

		set_error_handler(function($code, $message) {
			restore_error_handler();
			throw new DriverException($message, $code);
		}, E_ALL);

		$this->connection = pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);

		restore_error_handler();

		$this->simpleStorageTz = new DateTimeZone(isset($params['simple_storage_tz']) ? $params['simple_storage_tz'] : 'UTC');
		$this->connectionTz = new DateTimeZone(isset($params['connection_tz']) ? $params['connection_tz'] : date_default_timezone_get());
		$this->nativeQuery('SET TIME ZONE ' . pg_escape_literal($this->connectionTz->getName()));
	}


	public function disconnect()
	{
		if ($this->connection) {
			pg_close($this->connection);
			$this->connection = NULL;
		}
	}


	public function isConnected()
	{
		return $this->connection !== NULL;
	}


	/**
	 * This method is based on Doctrine\DBAL project.
	 * @link www.doctrine-project.org
	 */
	public function convertException(DriverException $exception)
	{
		// see codes at http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html

		$message = $exception->getMessage();
		$code = (string) $exception->getErrorSqlState();
		if ($code === '0A000' && strpos($message, 'truncate') !== FALSE) {
			// Foreign key constraint violations during a TRUNCATE operation
			// are considered "feature not supported" in PostgreSQL.
			return new Exceptions\ForeignKeyConstraintViolationException($message, $exception);

		} elseif ($code === '23502') {
			return new Exceptions\NotNullConstraintViolationException($message, $exception);

		} elseif ($code === '23503') {
			return new Exceptions\ForeignKeyConstraintViolationException($message, $exception);

		} elseif ($code === '23505') {
			return new Exceptions\UniqueConstraintViolationException($message, $exception);

		} elseif ($code === '' && stripos($message, 'pg_connect()') !== FALSE) {
			return new Exceptions\ConnectionException($message, $exception);

		} else {
			return new Exceptions\DbalException($message, $exception);
		}
	}


	public function getResourceHandle()
	{
		return $this->connection;
	}


	public function nativeQuery($query)
	{
		if (!pg_send_query($this->connection, $query)) {
			throw new DriverException(pg_last_error($this->connection));
		}

		$resource = pg_get_result($this->connection);
		if ($resource === FALSE) {
			throw new DriverException(pg_last_error($this->connection));
		}

		$state = pg_result_error_field($resource, PGSQL_DIAG_SQLSTATE);
		if ($state !== NULL) {
			throw new DriverException(pg_result_error($resource), 0, $state);
		}

		return new Result(new PostgreResultAdapter($resource), $this);
	}


	public function getLastInsertedId($sequenceName = NULL)
	{
		$sql = 'SELECT CURRVAL(' . pg_escape_literal($this->connection, $sequenceName) . ')';
		return $this->nativeQuery($sql)->fetchField();
	}


	public function createPlatform(Connection $connection)
	{
		return new PostgrePlatform($connection);
	}


	public function getServerVersion()
	{
		return pg_version($this->connection)['server'];
	}


	public function ping()
	{
		return pg_ping($this->connection);
	}


	public function transactionBegin()
	{
		$this->nativeQuery('START TRANSACTION');
	}


	public function transactionCommit()
	{
		$this->nativeQuery('COMMIT');
	}


	public function transactionRollback()
	{
		$this->nativeQuery('ROLLBACK');
	}


	public function convertToPhp($value, $nativeType)
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
			throw new Exceptions\NotSupportedException("PostgreDriver does not support '{$nativeType}' type conversion.");
		}
	}


	public function convertToSql($value, $type)
	{
		switch ($type) {
			case self::TYPE_STRING:
				return pg_escape_literal($this->connection, $value);

			case self::TYPE_BOOL:
				return $value ? '1' : '0';

			case self::TYPE_IDENTIFIER:
				$parts = explode('.', $value);
				foreach ($parts as &$part) {
					if ($part !== '*') {
						$part = pg_escape_identifier($this->connection, $part);
					}
				}
				return implode('.', $parts);

			case self::TYPE_DATETIME:
				return "'" . $value->format('Y-m-d H:i:sP') . "'";

			case self::TYPE_DATETIME_SIMPLE:
				if ($value->getTimezone()->getName() !== $this->simpleStorageTz->getName()) {
					$value = clone $value;
					$value->setTimezone($this->simpleStorageTz);
				}
				return "'" . $value->format('Y-m-d H:i:s') . "'";

			default:
				throw new Exceptions\InvalidArgumentException();
		}
	}


	public function modifyLimitQuery($query, $limit, $offset)
	{
		if ($limit !== NULL) {
			$query .= ' LIMIT ' . (int) $limit;
		}
		if ($offset !== NULL) {
			$query .= ' OFFSET ' . (int) $offset;
		}
		return $query;
	}

}
