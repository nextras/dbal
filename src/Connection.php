<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;


class Connection implements IConnection
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


	public function getDriver(): IDriver
	{
		return $this->driver;
	}


	/**
	 * Returns connection configuration.
	 */
	public function getConfig(): array
	{
		return $this->config;
	}


	/** @inheritdoc */
	public function query(...$args)
	{
		$this->connected || $this->connect();
		$sql = $this->sqlPreprocessor->process($args);

		$result = $this->driver->query($sql);

		$this->fireEvent('onQuery', [$this, $sql, $result]);
		return $result;
	}


	/** @inheritdoc */
	public function queryArgs($query, array $args = [])
	{
		if (!is_array($query)) {
			array_unshift($args, $query);
		} else {
			$args = $query;
		}
		return call_user_func_array([$this, 'query'], $args);
	}


	/** @inheritdoc */
	public function getLastInsertedId(string $sequenceName = NULL)
	{
		$this->connected || $this->connect();
		return $this->driver->getLastInsertedId($sequenceName);
	}


	/** @inheritdoc */
	public function getAffectedRows(): int
	{
		$this->connected || $this->connect();
		return $this->driver->getAffectedRows();
	}


	/** @inheritdoc */
	public function getPlatform(): IPlatform
	{
		if ($this->platform === NULL) {
			$this->connected || $this->connect();
			$this->platform = $this->driver->createPlatform($this);
		}

		return $this->platform;
	}


	/** @inheritdoc */
	public function createQueryBuilder(): QueryBuilder
	{
		return new QueryBuilder($this->driver);
	}


	/** @inheritdoc */
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


	/** @inheritdoc */
	public function beginTransaction()
	{
		$this->connected || $this->connect();
		$this->driver->beginTransaction();
		$this->fireEvent('onQuery', [$this, '::TRANSACTION BEGIN::']);
	}


	/** @inheritdoc */
	public function commitTransaction()
	{
		$this->driver->commitTransaction();
		$this->fireEvent('onQuery', [$this, '::TRANSACTION COMMIT::']);
	}


	/** @inheritdoc */
	public function rollbackTransaction()
	{
		$this->driver->rollbackTransaction();
		$this->fireEvent('onQuery', [$this, '::TRANSACTION ROLLBACK::']);
	}


	/** @inheritdoc */
	public function ping(): bool
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
	 */
	private function processConfig(array $config): array
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
	 */
	private function createDriver(array $config): IDriver
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
	 * @return void
	 */
	private function fireEvent(string $event, array $args)
	{
		foreach ($this->$event as $callback) {
			call_user_func_array($callback, $args);
		}
	}
}
