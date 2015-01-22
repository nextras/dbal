<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nette\Object;
use Nette\Utils\Callback;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\IDriverProvider;
use Nextras\Dbal\Drivers\IDriverException;
use Nextras\Dbal\Exceptions\NotImplementedException;
use Nextras\Dbal\Result\Rowset;


class Connection extends Object
{
	/** @var array of callbacks: function(Connection $connection) */
	public $onConnect = [];

	/** @var array of callbacks: function(Connection $connection) */
	public $onDisconnect = [];

	/** @var array of callbacks: function(Connection $connection, string $query) */
	public $onBeforeQuery = [];

	/** @var array of callbacks: function(Connection $connection, string $query, IRowset $rowset) */
	public $onAfterQuery = [];

	/** @var array */
	private $config;

	/** @var IDriver */
	private $driver;

	/** @var IDriverProvider */
	private $driverProvider;


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
		$this->fireEvent('onConnect', [$this]);
	}


	public function disconnect()
	{
		$this->driver = NULL;
		$this->fireEvent('onDisconnect', [$this]);
	}


	public function reconnect()
	{
		throw new NotImplementedException();
	}


	public function query($query)
	{
		$this->connect();
		$this->fireEvent('onBeforeQuery', [$this, $query]);

		try {
			$result = new Rowset($this->driver->nativeQuery($query), $this->driver);

		} catch (IDriverException $e) {
			throw $this->driverProvider->convertException($e->getMessage(), $e);
		}

		$this->fireEvent('onAfterQuery', [$this, $query, $result]);
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
		} catch (\Exception $e) {
			return FALSE;
		}
	}


	private function fireEvent($event, $args)
	{
		foreach ($this->$event as $callbacks) {
			Callback::invokeArgs($callbacks, $args);
		}
	}

}
