<?php declare(strict_types = 1);

namespace Nextras\Dbal;


use Exception;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\LoggerHelper;
use Nextras\Dbal\Utils\MultiLogger;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function array_unshift;
use function assert;
use function call_user_func_array;
use function is_array;
use function spl_object_hash;
use function ucfirst;


class Connection implements IConnection
{
	use StrictObjectTrait;


	/**
	 * @var array
	 * @phpstan-var array<string, mixed>
	 */
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

	/** @var MultiLogger */
	private $logger;


	/**
	 * @param array $config see drivers for supported options
	 * @phpstan-param array<string, mixed> $config
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
		$this->driver = $this->createDriver();
		$this->sqlPreprocessor = $this->createSqlProcessor();
		$this->connected = $this->driver->isConnected();
		$this->logger = new MultiLogger();
	}


	/** @inheritdoc */
	public function connect(): void
	{
		if ($this->connected) {
			return;
		}

		$this->driver->connect($this->config, $this->logger);
		$this->connected = true;
		$this->nestedTransactionIndex = 0;
		$this->nestedTransactionsWithSavepoint = (bool) ($this->config['nestedTransactionsWithSavepoint'] ?? true);
		$this->logger->onConnect();
	}


	/** @inheritdoc */
	public function disconnect(): void
	{
		if (!$this->connected) {
			return;
		}
		$this->driver->disconnect();
		$this->connected = false;
		$this->logger->onDisconnect();
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
		if (!$this->connected) {
			$this->connect();
		}
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
	public function getLastInsertedId(?string $sequenceName = null)
	{
		if (!$this->connected) {
			$this->connect();
		}
		return $this->driver->getLastInsertedId($sequenceName);
	}


	/** @inheritdoc */
	public function getAffectedRows(): int
	{
		if (!$this->connected) {
			$this->connect();
		}
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


	public function setTransactionIsolationLevel(int $level): void
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
		} catch (Exception $e) {
			$this->rollbackTransaction();
			throw $e;
		}
	}


	/** @inheritdoc */
	public function beginTransaction(): void
	{
		if (!$this->connected) {
			$this->connect();
		}

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
		if (!$this->connected) {
			$this->connect();
		}

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
		if (!$this->connected) {
			$this->connect();
		}

		if ($this->nestedTransactionIndex <= 1) {
			$this->driver->rollbackTransaction();
			$this->nestedTransactionIndex = 0;
		} elseif ($this->nestedTransactionsWithSavepoint) {
			$this->driver->rollbackSavepoint($this->getSavepointName());
			$this->nestedTransactionIndex--;
		}
	}


	/** @inheritdoc */
	public function getTransactionNestedIndex(): int
	{
		return $this->nestedTransactionIndex;
	}


	/** @inheritdoc */
	public function createSavepoint(string $name): void
	{
		if (!$this->connected) {
			$this->connect();
		}
		$this->driver->createSavepoint($name);
	}


	/** @inheritdoc */
	public function releaseSavepoint(string $name): void
	{
		if (!$this->connected) {
			$this->connect();
		}
		$this->driver->releaseSavepoint($name);
	}


	/** @inheritdoc */
	public function rollbackSavepoint(string $name): void
	{
		if (!$this->connected) {
			$this->connect();
		}
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


	public function addLogger(ILogger $logger): void
	{
		$this->logger->loggers[spl_object_hash($logger)] = $logger;
	}


	public function removeLogger(ILogger $logger): void
	{
		unset($this->logger->loggers[spl_object_hash($logger)]);
	}


	protected function getSavepointName(): string
	{
		return "NEXTRAS_SAVEPOINT_{$this->nestedTransactionIndex}";
	}


	private function nativeQuery(string $sql): Result
	{
		return LoggerHelper::loggedQuery(
			$this->driver,
			$this->logger,
			$sql
		);
	}


	private function createDriver(): IDriver
	{
		if (!isset($this->config['driver'])) {
			throw new InvalidArgumentException('Undefined driver. Choose from: mysqli, pgsql, sqlsrv.');
		} elseif ($this->config['driver'] instanceof IDriver) {
			return $this->config['driver'];
		} else {
			$name = ucfirst($this->config['driver']);
			/** @var class-string<IDriver> $class */
			$class = "Nextras\\Dbal\\Drivers\\{$name}\\{$name}Driver";
			return new $class();
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
}
