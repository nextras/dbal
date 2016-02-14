<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;


class Connection
{
	/** @var callable[]: function(Connection $connection) */
	public $onConnect = [];

	/** @var callable[]: function(Connection $connection) */
	public $onDisconnect = [];

	/** @var callable[]: function(Connection $connection, string $query, Result $result) */
	public $onQuery = [];

	/** @var array */
	private $config;

	/** @var IDriver */
	private $driver;

	/** @var IPlatform */
	private $platform;

	/** @var SqlProcessor */
	private $sqlPreprocessor;

	/** @var bool */
	private $connected;


	/**
	 * @param  array $config see drivers for supported options
	 */
	public function __construct(array $config)
	{
		$config = $this->processConfig($config);
		$this->config = $config;
		$this->driver = $this->createDriver($config);
		$this->sqlPreprocessor = new SqlProcessor($this->driver);
		$this->connected = $this->driver->isConnected();
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws ConnectionException
	 */
	public function connect()
	{
		if ($this->connected) {
			return;
		}
		$this->driver->connect($this->config);
		$this->connected = TRUE;
		$this->fireEvent('onConnect', [$this]);
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		if (!$this->connected) {
			return;
		}
		$this->driver->disconnect();
		$this->connected = FALSE;
		$this->fireEvent('onDisconnect', [$this]);
	}


	/**
	 * Reconnects to a database.
	 * @return void
	 */
	public function reconnect()
	{
		$this->disconnect();
		$this->connect();
	}


	/**
	 * Reconnects to a database with new configration.
	 * @param  array $config
	 */
	public function reconnectWithConfig(array $config)
	{
		$this->disconnect();
		$this->config = $this->processConfig($config);
		$this->driver = $this->createDriver($this->config);
		$this->sqlPreprocessor = new SqlProcessor($this->driver);
		$this->connect();
	}


	/**
	 * @return IDriver
	 */
	public function getDriver()
	{
		return $this->driver;
	}


	/**
	 * Returns connection configuration.
	 * @return array
	 */
	public function getConfig()
	{
		return $this->config;
	}


	/**
	 * Executes a query.
	 * @param  mixed ...$args
	 * @return Result|NULL
	 * @throws QueryException
	 */
	public function query(/*...$args*/)
	{
		$this->connected || $this->connect();
		$args = func_get_args();
		$sql = $this->sqlPreprocessor->process($args);

		$result = $this->driver->query($sql);

		$this->fireEvent('onQuery', [$this, $sql, $result]);
		return $result;
	}


	/**
	 * @param  string|array $query
	 * @param  array $args
	 * @return Result|NULL
	 * @throws QueryException
	 */
	public function queryArgs($query, array $args = [])
	{
		if (!is_array($query)) {
			array_unshift($args, $query);
		} else {
			$args = $query;
		}
		return call_user_func_array([$this, 'query'], $args);
	}


	/**
	 * Returns last inserted ID.
	 * @param  string|NULL $sequenceName
	 * @return int|string
	 */
	public function getLastInsertedId($sequenceName = NULL)
	{
		$this->connected || $this->connect();
		return $this->driver->getLastInsertedId($sequenceName);
	}


	/**
	 * Returns number of affected rows.
	 * @return int
	 */
	public function getAffectedRows()
	{
		$this->connected || $this->connect();
		return $this->driver->getAffectedRows();
	}


	/**
	 * @return IPlatform
	 */
	public function getPlatform()
	{
		if ($this->platform === NULL) {
			$this->connected || $this->connect();
			$this->platform = $this->driver->createPlatform($this);
		}

		return $this->platform;
	}


	/**
	 * Creates new QueryBuilder instance.
	 * @return QueryBuilder
	 */
	public function createQueryBuilder()
	{
		return new QueryBuilder($this->driver);
	}


	/**
	 * Performs operation in a transaction.
	 * @param  callable $callback function(Connection $conn): mixed
	 * @return mixed value returned by callback
	 * @throws \Exception
	 */
	public function transactional(callable $callback)
	{
		$this->beginTransaction();
		try {
			$returnValue = $callback($this);
			$this->commitTransaction();
			return $returnValue;

		} catch (\Exception $e) {
			$this->rollbackTransaction();
			throw $e;
		}
	}


	/**
	 * Starts a transaction.
	 * @return void
	 * @throws DriverException
	 */
	public function beginTransaction()
	{
		$this->connected || $this->connect();
		$this->driver->beginTransaction();
		$this->fireEvent('onQuery', [$this, '::TRANSACTION BEGIN::']);
	}


	/**
	 * Commits the current transaction.
	 * @return void
	 * @throws DriverException
	 */
	public function commitTransaction()
	{
		$this->driver->commitTransaction();
		$this->fireEvent('onQuery', [$this, '::TRANSACTION COMMIT::']);
	}


	/**
	 * Cancels any uncommitted changes done during the current transaction.
	 * @return void
	 * @throws DriverException
	 */
	public function rollbackTransaction()
	{
		$this->driver->rollbackTransaction();
		$this->fireEvent('onQuery', [$this, '::TRANSACTION ROLLBACK::']);
	}


	/**
	 * Pings a database connection and tries to reconnect it if it is broken.
	 * @return bool
	 */
	public function ping()
	{
		try {
			$this->connected || $this->connect();
			return $this->driver->ping();

		} catch (DriverException $e) {
			return FALSE;
		}
	}


	/**
	 * Processes config: fills defaults, creates aliases, processes dynamic values.
	 * @param  array $config
	 * @return array
	 */
	private function processConfig(array $config)
	{
		if (!isset($config['dbname']) && isset($config['database'])) {
			$config['dbname'] = $config['database'];
		}
		if (!isset($config['user']) && isset($config['username'])) {
			$config['user'] = $config['username'];
		}
		if (!isset($config['simpleStorageTz'])) {
			$config['simpleStorageTz'] = 'UTC';
		}
		if (!isset($config['connectionTz']) || $config['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_NAME) {
			$config['connectionTz'] = date_default_timezone_get();
		} elseif ($config['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_OFFSET) {
			$config['connectionTz'] = date('P');
		}
		if (!isset($config['sqlMode'])) { // only for MySQL
			$config['sqlMode'] = 'TRADITIONAL';
		}
		return $config;
	}


	/**
	 * Creates a IDriver instance.
	 * @param  array $config
	 * @return IDriver
	 */
	private function createDriver(array $config)
	{
		if (empty($config['driver'])) {
			throw new InvalidStateException('Undefined driver. Choose from: mysqli, pgsql.');

		} elseif ($config['driver'] instanceof IDriver) {
			return $config['driver'];

		} else {
			$name = ucfirst($config['driver']);
			$class = "Nextras\\Dbal\\Drivers\\{$name}\\{$name}Driver";
			return new $class;
		}
	}


	/**
	 * @param  string $event
	 * @param  array $args
	 * @return void
	 */
	private function fireEvent($event, array $args)
	{
		foreach ($this->$event as $callback) {
			call_user_func_array($callback, $args);
		}
	}
}
