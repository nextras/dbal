<?php declare(strict_types = 1);

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


class Connection implements IConnection
{
	/** @var callable[]: function(Connection $connection) */
	public $onConnect = [];

	/** @var callable[]: function(Connection $connection) */
	public $onDisconnect = [];

	/** @var callable[]: function(Connection $connection, string $query, float $time, ?Result $result, ?DriverException $exception) */
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

	/** @var int */
	private $nestedTransactionIndex = 0;

	/** @var bool */
	private $nestedTransactionsWithSavepoint = true;


	/**
	 * @param  array $config see drivers for supported options
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
		$this->driver = $this->createDriver();
		$this->sqlPreprocessor = $this->createSqlProcessor();
		$this->connected = $this->driver->isConnected();
	}


	/** @inheritdoc */
	public function connect(): void
	{
		if ($this->connected) {
			return;
		}
		$this->driver->connect($this->config, function (string $sql, float $time, Result $result = null, DriverException $exception = null) {
			$this->fireEvent('onQuery', [$this, $sql, $time, $result, $exception]);
		});
		$this->connected = true;
		$this->nestedTransactionIndex = 0;
		$this->nestedTransactionsWithSavepoint = (bool) ($this->config['nestedTransactionsWithSavepoint'] ?? true);
		$this->fireEvent('onConnect', [$this]);
	}


	/** @inheritdoc */
	public function disconnect(): void
	{
		if (!$this->connected) {
			return;
		}
		$this->driver->disconnect();
		$this->connected = false;
		$this->fireEvent('onDisconnect', [$this]);
	}


	/** @inheritdoc */
	public function reconnect(): void
	{
		$this->disconnect();
		$this->connect();
	}


	/** @inheritdoc */
	public function reconnectWithConfig(array $config): void
	{
		$this->disconnect();
		$this->config = $config + $this->config;
		$this->driver = $this->createDriver();
		$this->sqlPreprocessor = $this->createSqlProcessor();
		$this->connect();
	}


	public function getDriver(): IDriver
	{
		return $this->driver;
	}


	/** @inheritdoc */
	public function getConfig(): array
	{
		return $this->config;
	}


	/** @inheritdoc */
	public function query(...$args): Result
	{
		if (!$this->connected) $this->connect();
		$sql = $this->sqlPreprocessor->process($args);
		return $this->nativeQuery($sql);
	}


	/** @inheritdoc */
	public function queryArgs($query, array $args = []): Result
	{
		if (!is_array($query)) {
			array_unshift($args, $query);
		} else {
			$args = $query;
		}
		return call_user_func_array([$this, 'query'], $args);
	}


	public function queryByQueryBuilder(QueryBuilder $queryBuilder): Result
	{
		return $this->queryArgs($queryBuilder->getQuerySql(), $queryBuilder->getQueryParameters());
	}


	/** @inheritdoc */
	public function getLastInsertedId(string $sequenceName = null)
	{
		if (!$this->connected) $this->connect();
		return $this->driver->getLastInsertedId($sequenceName);
	}


	/** @inheritdoc */
	public function getAffectedRows(): int
	{
		if (!$this->connected) $this->connect();
		return $this->driver->getAffectedRows();
	}


	/** @inheritdoc */
	public function getPlatform(): IPlatform
	{
		if ($this->platform === null) {
			$this->platform = $this->driver->createPlatform($this);
		}

		return $this->platform;
	}


	/** @inheritdoc */
	public function createQueryBuilder(): QueryBuilder
	{
		return new QueryBuilder($this->driver);
	}


	public function setTransactionIsolationLevel(int $level)
	{
		$this->driver->setTransactionIsolationLevel($level);
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
	public function beginTransaction(): void
	{
		if (!$this->connected) $this->connect();

		if ($this->nestedTransactionIndex === 0) {
			$this->nestedTransactionIndex++;
			$this->driver->beginTransaction();
		} elseif ($this->nestedTransactionsWithSavepoint) {
			$this->nestedTransactionIndex++;
			$this->driver->createSavepoint($this->getSavepointName());
		}
	}


	/** @inheritdoc */
	public function commitTransaction(): void
	{
		if (!$this->connected) $this->connect();

		if ($this->nestedTransactionIndex <= 1) {
			$this->driver->commitTransaction();
			$this->nestedTransactionIndex = 0;

		} elseif ($this->nestedTransactionsWithSavepoint) {
			$this->driver->releaseSavepoint($this->getSavepointName());
			$this->nestedTransactionIndex--;
		}
	}


	/** @inheritdoc */
	public function rollbackTransaction(): void
	{
		if (!$this->connected) $this->connect();

		if ($this->nestedTransactionIndex <= 1) {
			$this->driver->rollbackTransaction();
			$this->nestedTransactionIndex = 0;

		} elseif ($this->nestedTransactionsWithSavepoint) {
			$this->driver->rollbackSavepoint($this->getSavepointName());
			$this->nestedTransactionIndex--;
		}
	}


	/**
	 * Returns current connection's transaction index.
	 * 0 = no running transaction
	 * 1 = basic transaction
	 * >1 = nested transaction through savepoints
	 * Todo: Add this method to interface in v4
	 * @return int
	 */
	public function getTransactionIndex(): int
	{
		return $this->nestedTransactionIndex;
	}


	/** @inheritdoc */
	public function createSavepoint(string $name): void
	{
		if (!$this->connected) $this->connect();
		$this->driver->createSavepoint($name);
	}


	/** @inheritdoc */
	public function releaseSavepoint(string $name): void
	{
		if (!$this->connected) $this->connect();
		$this->driver->releaseSavepoint($name);
	}


	/** @inheritdoc */
	public function rollbackSavepoint(string $name): void
	{
		if (!$this->connected) $this->connect();
		$this->driver->rollbackSavepoint($name);
	}


	/** @inheritdoc */
	public function ping(): bool
	{
		if (!$this->connected) {
			return false;
		}
		return $this->driver->ping();
	}


	protected function getSavepointName(): string
	{
		return "NEXTRAS_SAVEPOINT_{$this->nestedTransactionIndex}";
	}


	private function nativeQuery(string $sql): Result
	{
		try {
			$result = $this->driver->query($sql);
			$this->fireEvent('onQuery', [
				$this,
				$sql,
				$this->driver->getQueryElapsedTime(),
				$result,
				null, // exception
			]);
			return $result;
		} catch (DriverException $exception) {
			$this->fireEvent('onQuery', [
				$this,
				$sql,
				$this->driver->getQueryElapsedTime(),
				null, // result
				$exception
			]);
			throw $exception;
		}
	}


	private function createDriver(): IDriver
	{
		if (empty($this->config['driver'])) {
			throw new InvalidStateException('Undefined driver. Choose from: mysqli, pgsql.');

		} elseif ($this->config['driver'] instanceof IDriver) {
			return $this->config['driver'];

		} else {
			$name = ucfirst($this->config['driver']);
			$class = "Nextras\\Dbal\\Drivers\\{$name}\\{$name}Driver";
			return new $class;
		}
	}


	private function createSqlProcessor(): SqlProcessor
	{
		if (isset($this->config['sqlProcessorFactory'])) {
			$factory = $this->config['sqlProcessorFactory'];
			assert($factory instanceof ISqlProcessorFactory);
			return $factory->create($this);
		} else {
			return new SqlProcessor($this->driver, $this->getPlatform());
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
