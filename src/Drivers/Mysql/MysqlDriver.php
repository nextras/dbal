<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mysql;

use DateInterval;
use mysqli;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exceptions\InvalidArgumentException;
use Nextras\Dbal\Exceptions\NotSupportedException;
use Nextras\Dbal\Result\Rowset;


class MysqlDriver implements IDriver
{
	/** @const name of key in params for passing flags to connection */
	const PARAMS_FLAGS = 'flags';

	/** @var mysqli */
	private $connection;


	public function __construct(array $params, $username, $password)
	{
		$host   = isset($params['host']) ? $params['host'] : ini_get('mysqli.default_host');
		$port   = isset($params['port']) ? $params['port'] : ini_get('mysqli.default_port');
		$port   = $port ?: 3306;
		$dbname = isset($params['dbname']) ? $params['dbname'] : NULL;
		$socket = isset($params['unix_socket']) ? $params['unix_socket'] : ini_get('mysqli.default_socket');
		$flags  = isset($params[self::PARAMS_FLAGS]) ? $params[self::PARAMS_FLAGS] : 0;

		$this->connection = mysqli_init();

		if (!$this->connection->real_connect($host, $username, $password, $dbname, $port, $socket, $flags)) {
			throw new MysqlException(
				$this->connection->connect_error,
				$this->connection->connect_errno,
				@$this->connection->sqlstate ?: 'HY000'
			);
		}

		$this->processInitialSettings($params);
	}


	public function __destruct()
	{
		$this->connection->close();
	}


	/** @return mysqli */
	public function getResourceHandle()
	{
		return $this->connection;
	}


	public function nativeQuery($query)
	{
		$result = $this->connection->query($query);
		if ($this->connection->errno) {
			throw new MysqlException(
				$this->connection->error,
				$this->connection->errno,
				$this->connection->sqlstate
			);
		}

		return new Rowset(new MysqlRowsetAdapter($result), $this);
	}


	public function getLastInsertedId($sequenceName = NULL)
	{
		return $this->connection->insert_id;
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
			$this->nativeQuery('SET sql_mode = \'' . $this->connection->escape_string($params['sqlMode']) . '\'');
		}
	}


	public function convertToPhp($value, $nativeType)
	{
		if ($nativeType === MYSQLI_TYPE_TIME) {
			preg_match('#^(-?)(\d+):(\d+):(\d+)#', $value, $m);
			$value = new DateInterval("PT{$m[2]}H{$m[3]}M{$m[4]}S");
			$value->invert = $m[1] ? 1 : 0;
			return $value;

		} else {
			throw new NotSupportedException("MysqlDriverProvider does not support '{$nativeType}' type conversion.");
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
				return '`' . str_replace('`', '``', $value) . '`';

			default:
				throw new InvalidArgumentException();
		}
	}


	public function getTokenRegexp()
	{
		return '(?:`.*?`|".*?"|\'.*?\')';
	}

}
