<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mysqli;

use DateInterval;
use DateTimeZone;
use mysqli;
use Nextras\Dbal\Connection;
use Nextras\Dbal\ConnectionException;
use Nextras\Dbal\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\ForeignKeyConstraintViolationException;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\NotNullConstraintViolationException;
use Nextras\Dbal\NotSupportedException;
use Nextras\Dbal\Platforms\MySqlPlatform;
use Nextras\Dbal\QueryException;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\UniqueConstraintViolationException;


class MysqliDriver implements IDriver
{
	/** @var mysqli */
	private $connection;

	/** @var DateTimeZone Timezone for columns without timezone handling (datetime). */
	private $simpleStorageTz;

	/** @var DateTimeZone Timezone for database connection. */
	private $connectionTz;


	public function __destruct()
	{
		$this->disconnect();
	}


	public function connect(array $params)
	{
		$host   = isset($params['host']) ? $params['host'] : ini_get('mysqli.default_host');
		$port   = isset($params['port']) ? $params['port'] : (ini_get('mysqli.default_port') ?: 3306);
		$dbname = isset($params['dbname']) ? $params['dbname'] : '';
		$socket = isset($params['unix_socket']) ? $params['unix_socket'] : (ini_get('mysqli.default_socket') ?: NULL);
		$flags  = isset($params['flags']) ? $params['flags'] : 0;

		$this->connection = new mysqli();

		if (!@$this->connection->real_connect($host, $params['user'], $params['password'], $dbname, $port, $socket, $flags)) {
			throw $this->createException(
				$this->connection->connect_error,
				$this->connection->connect_errno,
				@$this->connection->sqlstate ?: 'HY000'
			);
		}

		$this->processInitialSettings($params);
	}


	public function disconnect()
	{
		if ($this->connection) {
			$this->connection->close();
			$this->connection = NULL;
		}
	}


	public function isConnected()
	{
		return $this->connection !== NULL;
	}


	/** @return mysqli */
	public function getResourceHandle()
	{
		return $this->connection;
	}


	public function query($query)
	{
		$time = microtime(TRUE);
		$result = @$this->connection->query($query);
		$time = microtime(TRUE) - $time;

		if ($this->connection->errno) {
			throw $this->createException(
				$this->connection->error,
				$this->connection->errno,
				$this->connection->sqlstate,
				$query
			);
		}

		if ($result === TRUE) {
			return NULL;
		}

		return new Result(new MysqliResultAdapter($result), $this, $time);
	}


	public function getLastInsertedId($sequenceName = NULL)
	{
		return $this->connection->insert_id;
	}


	public function getAffectedRows()
	{
		return $this->connection->affected_rows;
	}


	public function createPlatform(Connection $connection)
	{
		return new MySqlPlatform($connection);
	}


	public function getServerVersion()
	{
		$version = $this->connection->server_version;
		$majorVersion = floor($version / 10000);
		$minorVersion = floor(($version - $majorVersion * 10000) / 100);
		$patchVersion = floor($version - $majorVersion * 10000 - $minorVersion * 100);
		return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
	}


	public function ping()
	{
		return $this->connection->ping();
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


	protected function processInitialSettings(array $params)
	{
		if (isset($params['charset'])) {
			$charset = $params['charset'];
		} elseif (($version = $this->getServerVersion()) && version_compare($version, '5.5.3', '>=')) {
			$charset = 'utf8mb4';
		} else {
			$charset = 'utf8';
		}

		$this->connection->set_charset($charset);

		if (isset($params['sqlMode'])) {
			$this->query('SET sql_mode = ' . $this->convertToSql($params['sqlMode'], self::TYPE_STRING));
		}

		$this->simpleStorageTz = new DateTimeZone($params['simpleStorageTz']);
		$this->connectionTz = new DateTimeZone($params['connectionTz']);
		$this->query('SET time_zone = ' . $this->convertToSql($this->connectionTz->getName(), self::TYPE_STRING));
	}


	public function convertToPhp($value, $nativeType)
	{
		if ($nativeType === MYSQLI_TYPE_DATETIME || $nativeType === MYSQLI_TYPE_DATE) {
			return $value . ' ' . $this->simpleStorageTz->getName();

		} elseif ($nativeType === MYSQLI_TYPE_TIMESTAMP) {
			return $value . ' ' . $this->connectionTz->getName();

		} elseif ($nativeType === MYSQLI_TYPE_LONGLONG) {
			// called only on 32bit
			return is_float($tmp = $value * 1) ? $value : $tmp;

		} elseif ($nativeType === MYSQLI_TYPE_TIME) {
			preg_match('#^(-?)(\d+):(\d+):(\d+)#', $value, $m);
			$value = new DateInterval("PT{$m[2]}H{$m[3]}M{$m[4]}S");
			$value->invert = $m[1] ? 1 : 0;
			return $value;

		} elseif ($nativeType === MYSQLI_TYPE_BIT) {
			// called only under HHVM
			return ord($value);

		} else {
			throw new NotSupportedException("MysqliDriver does not support '{$nativeType}' type conversion.");
		}
	}


	public function convertToSql($value, $type)
	{
		switch ($type) {
			case self::TYPE_STRING:
				return "'" . $this->connection->escape_string($value) . "'";

			case self::TYPE_BOOL:
				return $value ? '1' : '0';

			case self::TYPE_IDENTIFIER:
				return str_replace('`*`', '*', '`' . str_replace(['`', '.'], ['``', '`.`'], $value) . '`');

			case self::TYPE_DATETIME:
				if ($value->getTimezone()->getName() !== $this->connectionTz->getName()) {
					$value = clone $value;
					$value->setTimezone($this->connectionTz);
				}
				return "'" . $value->format('Y-m-d H:i:s') . "'";

			case self::TYPE_DATETIME_SIMPLE:
				if ($value->getTimezone()->getName() !== $this->simpleStorageTz->getName()) {
					$value = clone $value;
					$value->setTimeZone($this->simpleStorageTz);
				}
				return "'" . $value->format('Y-m-d H:i:s') . "'";

			case self::TYPE_DATE_INTERVAL:
				$totalHours = ((int) $value->format('%a')) * 24 + $value->h;
				if ($totalHours >= 839) {
					// see https://dev.mysql.com/doc/refman/5.0/en/time.html
					throw new InvalidArgumentException('Mysql cannot store interval bigger than 839h:59m:59s.');
				}
				return $value->format("%r{$totalHours}:%S:%I");

			default:
				throw new InvalidArgumentException();
		}
	}


	public function modifyLimitQuery($query, $limit, $offset)
	{
		if ($limit !== NULL || $offset !== NULL) {
			// 18446744073709551615 is maximum of unsigned BIGINT
			// see http://dev.mysql.com/doc/refman/5.0/en/select.html
			$query .= ' LIMIT ' . ($limit !== NULL ? (int) $limit : '18446744073709551615');
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
		if (in_array($errorNo, [1216, 1217, 1451, 1452, 1701], TRUE)) {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif (in_array($errorNo, [1062, 1557, 1569, 1586], TRUE)) {
			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif (in_array($errorNo, [1044, 1045, 1046, 1049, 1095, 1142, 1143, 1227, 1370, 2002, 2005], TRUE)) {
			return new ConnectionException($error, $errorNo, $sqlState);

		} elseif (in_array($errorNo, [1048, 1121, 1138, 1171, 1252, 1263, 1566], TRUE)) {
			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, NULL, $query);

		} elseif ($query !== NULL) {
			return new QueryException($error, $errorNo, $sqlState, NULL, $query);

		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}

}
