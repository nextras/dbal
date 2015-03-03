<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nextras\Dbal\Drivers\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exceptions\DbalException;
use Nextras\Dbal\Platforms\IPlatform;
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


	/**
	 * @param  array $config see drivers for supported options
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
		$this->driver = $this->createDriver($config);
		$this->sqlPreprocessor = new SqlProcessor($this->driver);
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws DbalException
	 */
	public function connect()
	{
		if ($this->driver->isConnected()) {
			return;
		}

		try {
			$this->driver->connect($this->config);
		} catch (DriverException $e) {
			throw $this->driver->convertException($e);
		}

		$this->fireEvent('onConnect', [$this]);
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		$this->driver->disconnect();
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
	 *
	 * @param  mixed       ...$args
	 * @return Result|NULL
	 * @throws DbalException
	 */
	public function query(/*...$args*/)
	{
		$this->connect();
		$args = func_get_args();
		$sql = $this->sqlPreprocessor->process($args);

		try {
			$result = $this->driver->nativeQuery($sql);

		} catch (DriverException $e) {
			throw $this->driver->convertException($e);
		}

		$this->fireEvent('onQuery', [$this, $sql, $result]);
		return $result;
	}


	/**
	 * @param  string $query
	 * @param  array  $args
	 * @return Result|NULL
	 * @throws DbalException
	 */
	public function queryArgs($query, array $args)
	{
		array_unshift($args, $query);
		return call_user_func_array([$this, 'query'], $args);
	}


	/**
	 * Returns last inserted ID.
	 * @param  string|NULL $sequenceName
	 * @return int|string
	 */
	public function getLastInsertedId($sequenceName = NULL)
	{
		$this->connect();
		return $this->driver->getLastInsertedId($sequenceName);
	}


	/**
	 * @return IPlatform
	 */
	public function getPlatform()
	{
		if ($this->platform === NULL) {
			$this->connect();
			$this->platform = $this->driver->createPlatform($this);
		}

		return $this->platform;
	}


	/**
	 * Starts a transaction.
	 * @return void
	 * @throws DbalException
	 */
	public function transactionBegin()
	{
		$this->connect();
		try {
			$this->driver->transactionBegin();
		} catch (DriverException $e) {
			throw $this->driver->convertException($e);
		}
		$this->fireEvent('onQuery', [$this, '::TRANSACTION BEGIN::']);
	}


	/**
	 * Commits the current transaction.
	 * @return void
	 * @throws DbalException
	 */
	public function transactionCommit()
	{
		$this->connect();
		try {
			$this->driver->transactionCommit();
		} catch (DriverException $e) {
			throw $this->driver->convertException($e);
		}
		$this->fireEvent('onQuery', [$this, '::TRANSACTION COMMIT::']);
	}


	/**
	 * Cancels any uncommitted changes done during the current transaction.
	 * @return void
	 * @throws DbalException
	 */
	public function transactionRollback()
	{
		$this->connect();
		try {
			$this->driver->transactionRollback();
		} catch (DriverException $e) {
			throw $this->driver->convertException($e);
		}
		$this->fireEvent('onQuery', [$this, '::TRANSACTION ROLLBACK::']);
	}


	/**
	 * Pings a database connection and tries to reconnect it if it is broken.
	 * @return bool
	 */
	public function ping()
	{
		$this->connect();
		try {
			return $this->driver->ping();

		} catch (DriverException $e) {
			return FALSE;
		}
	}


	/**
	 * Creates a IDriver instance.
	 * @param  array $config
	 * @return IDriver
	 */
	private function createDriver(array $config)
	{
		if ($config['driver'] instanceof IDriver) {
			return $config['driver'];

		} else {
			$name = ucfirst($config['driver']);
			$class = "Nextras\\Dbal\\Drivers\\{$name}\\{$name}Driver";
			return new $class;
		}
	}


	/**
	 * @param  string $event
	 * @param  array  $args
	 * @return void
	 */
	private function fireEvent($event, array $args)
	{
		foreach ($this->$event as $callback) {
			call_user_func_array($callback, $args);
		}
	}

}
