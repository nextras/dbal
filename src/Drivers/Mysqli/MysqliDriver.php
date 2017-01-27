<?php declare(strict_types=1);

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
use Nextras\Dbal\Platforms\IPlatform;
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

	/** @var callable */
	private $loggedQueryCallback;

	/** @var float */
	private $timeTaken = 0.0;


	public function __destruct()
	{
		$this->disconnect();
	}


	public function connect(array $params, callable $loggedQueryCallback)
	{
		$this->loggedQueryCallback = $loggedQueryCallback;

		$host = $params['host'] ?? ini_get('mysqli.default_host');
		$port = $params['port'] ?? (int) (ini_get('mysqli.default_port') ?: 3306);
		$dbname = $params['dbname'] ?? '';
		$socket = $params['unix_socket'] ?? ini_get('mysqli.default_socket') ?? '';
		$flags = $params['flags'] ?? 0;

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


	public function isConnected(): bool
	{
		return $this->connection !== NULL;
	}


	public function getResourceHandle(): mysqli
	{
		return $this->connection;
	}


	public function query(string $query)
	{
		$time = microtime(TRUE);
		$result = @$this->connection->query($query);
		$this->timeTaken = microtime(TRUE) - $time;

		if ($result === FALSE) {
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

		return new Result(new MysqliResultAdapter($result), $this);
	}


	public function getLastInsertedId(string $sequenceName = NULL)
	{
		return $this->connection->insert_id;
	}


	public function getAffectedRows(): int
	{
		return $this->connection->affected_rows;
	}


	public function getQueryElapsedTime(): float
	{
		return $this->timeTaken;
	}


	public function createPlatform(Connection $connection): IPlatform
	{
		return new MySqlPlatform($connection);
	}


	public function getServerVersion(): string
	{
		$version = $this->connection->server_version;
		$majorVersion = floor($version / 10000);
		$minorVersion = floor(($version - $majorVersion * 10000) / 100);
		$patchVersion = floor($version - $majorVersion * 10000 - $minorVersion * 100);
		return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
	}


	public function ping(): bool
	{
		return $this->connection->ping();
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
		$this->loggedQuery("SET SESSION TRANSACTION ISOLATION LEVEL {$levels[$level]}");
	}


	public function beginTransaction()
	{
		$this->loggedQuery('START TRANSACTION');
	}


	public function commitTransaction()
	{
		$this->loggedQuery('COMMIT');
	}


	public function rollbackTransaction()
	{
		$this->loggedQuery('ROLLBACK');
	}


	public function createSavepoint(string $name)
	{
		$this->loggedQuery('SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function releaseSavepoint(string $name)
	{
		$this->loggedQuery('RELEASE SAVEPOINT ' . $this->convertIdentifierToSql($name));
	}


	public function rollbackSavepoint(string $name)
	{
		$this->loggedQuery('ROLLBACK TO SAVEPOINT ' . $this->convertIdentifierToSql($name));
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
			$this->loggedQuery('SET sql_mode = ' . $this->convertStringToSql($params['sqlMode']));
		}

		$this->simpleStorageTz = new DateTimeZone($params['simpleStorageTz']);
		$this->connectionTz = new DateTimeZone($params['connectionTz']);
		$this->loggedQuery('SET time_zone = ' . $this->convertStringToSql($this->connectionTz->getName()));
	}


	public function convertToPhp(string $value, $nativeType)
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


	public function convertStringToSql(string $value): string
	{
		return "'" . $this->connection->escape_string($value) . "'";
	}


	public function convertJsonToSql($value): string
	{
		$encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
		if (json_last_error()) {
			throw new InvalidArgumentException('JSON Encode Error: ' . json_last_error_msg());
		}
		return $this->convertStringToSql($encoded);
	}


	public function convertLikeToSql(string $value, int $mode): string
	{
		$value = addcslashes(str_replace('\\', '\\\\', $value), "\x00\n\r\\'%_");
		return ($mode <= 0 ? "'%" : "'") . $value . ($mode >= 0 ? "%'" : "'");
	}


	public function convertBoolToSql(bool $value): string
	{
		return $value ? '1' : '0';
	}


	public function convertIdentifierToSql(string $value): string
	{
		return str_replace('`*`', '*', '`' . str_replace(['`', '.'], ['``', '`.`'], $value) . '`');
	}


	public function convertDatetimeToSql(\DateTimeInterface $value): string
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
		return "'" . $value->format('Y-m-d H:i:s') . "'";
	}


	public function convertDatetimeSimpleToSql(\DateTimeInterface $value): string
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
		return "'" . $value->format('Y-m-d H:i:s') . "'";
	}


	public function convertDateIntervalToSql(\DateInterval $value): string
	{
		$totalHours = ((int) $value->format('%a')) * 24 + $value->h;
		if ($totalHours >= 839) {
			// see https://dev.mysql.com/doc/refman/5.0/en/time.html
			throw new InvalidArgumentException('Mysql cannot store interval bigger than 839h:59m:59s.');
		}
		return "'" . $value->format("%r{$totalHours}:%I:%S") . "'";
	}


	public function convertBlobToSql(string $value): string
	{
		return "_binary'" . $this->connection->escape_string($value) . "'";
	}


	public function modifyLimitQuery(string $query, $limit, $offset): string
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


	protected function loggedQuery(string $sql)
	{
		return ($this->loggedQueryCallback)($sql);
	}
}
