<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\IDriverProvider;
use Nextras\Dbal\Drivers\IDriverException;
use Nextras\DBAL\Exceptions\DbalException;
use Nextras\Dbal\Exceptions\NotImplementedException;
use Nextras\Dbal\Result\Result;


class Connection
{
	/** @var array of callbacks: function(Connection $connection) */
	public $onConnect = [];

	/** @var array of callbacks: function(Connection $connection) */
	public $onDisconnect = [];

	/** @var array of callbacks: function(Connection $connection, string $query) */
	public $onBeforeQuery = [];

	/** @var array of callbacks: function(Connection $connection, string $query, Result $rowset) */
	public $onAfterQuery = [];

	/** @var array */
	private $config;

	/** @var IDriver */
	private $driver;

	/** @var IDriverProvider */
	private $driverProvider;

	/** @var SqlProcessor */
	private $sqlPreprocessor;


	public function __construct(array $config)
	{
		$this->config = $config;

		$provider = $config['driver'];
		if (is_object($provider)) {
			$this->driverProvider = $provider;
		} else {
			$provider = ucfirst($provider);
			$provider = "Nextras\\Dbal\\Drivers\\{$provider}\\{$provider}DriverProvider";
			$this->driverProvider = new $provider;
		}
	}


	public function connect()
	{
		if ($this->driver) {
			return;
		}

		$this->driver = $this->driverProvider->connect($this->config, $this->config['username'], $this->config['password']);
		$this->sqlPreprocessor = new SqlProcessor($this->driver);
		$this->fireEvent('onConnect', [$this]);
	}


	public function disconnect()
	{
		$this->driver = NULL;
		$this->sqlPreprocessor = NULL;
		$this->fireEvent('onDisconnect', [$this]);
	}


	public function reconnect()
	{
		throw new NotImplementedException();
	}


	/**
	 * @param  string $query
	 * @return Result
	 * @throws DbalException
	 */
	public function query($query)
	{
		$this->connect();
		$sql = $this->sqlPreprocessor->process(func_get_args());
		$this->fireEvent('onBeforeQuery', [$this, $sql]);

		try {
			$result = $this->driver->nativeQuery($sql);

		} catch (IDriverException $e) {
			throw $this->driverProvider->convertException($e->getMessage(), $e);
		}

		$this->fireEvent('onAfterQuery', [$this, $sql, $result]);
		return $result;
	}


	public function getLastInsertedId($sequenceName = NULL)
	{
		$this->connect();
		return $this->driver->getLastInsertedId($sequenceName);
	}


	public function transactionBegin()
	{
		throw new NotImplementedException();
	}


	public function transactionCommit()
	{
		throw new NotImplementedException();
	}


	public function transactionRollback()
	{
		throw new NotImplementedException();
	}


	public function ping()
	{
		$this->connect();
		try {
			return $this->driver->ping();
		} catch (IDriverException $e) {
			return FALSE;
		}
	}


	private function fireEvent($event, $args)
	{
		foreach ($this->$event as $callback) {
			call_user_func_array($callback, $args);
		}
	}

}
