<?php declare(strict_types = 1);

namespace Nextras\Dbal;


use Exception;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\LoggerHelper;
use Nextras\Dbal\Utils\MultiLogger;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function array_unshift;
use function is_array;
use function spl_object_hash;
use function str_replace;
use function ucfirst;
use function ucwords;


class Connection implements IConnection
{
	use StrictObjectTrait;


	private IDriver $driver;
	private ?IPlatform $platform = null;
	private SqlProcessor $sqlPreprocessor;
	private bool $connected;
	private int $nestedTransactionIndex = 0;
	private bool $nestedTransactionsWithSavepoint = true;
	private MultiLogger $logger;


	/**
	 * @param array<string, mixed> $config see drivers for supported options
	 */
	public function __construct(private array $config)
	{
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
	public function query(string $expression, ...$args): Result
	{
		if (!$this->connected) {
			$this->connect();
		}
		array_unshift($args, $expression);
		$sql = $this->sqlPreprocessor->process($args);
		return $this->nativeQuery($sql);
	}


	/** @inheritdoc */
	public function queryArgs($query, array $args = []): Result
	{
		if (!$this->connected) {
			$this->connect();
		}
		if (is_array($query)) {
			$args = $query;
		} else {
			array_unshift($args, $query);
		}
		$sql = $this->sqlPreprocessor->process($args);
		return $this->nativeQuery($sql);
	}


	public function queryByQueryBuilder(QueryBuilder $queryBuilder): Result
	{
		return $this->queryArgs(
			$queryBuilder->getQuerySql(),
			$queryBuilder->getQueryParameters(),
		);
	}


	/** @inheritdoc */
	public function getLastInsertedId(string|Fqn|null $sequenceName = null)
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
		return new QueryBuilder($this->getPlatform());
	}


	public function setTransactionIsolationLevel(int $level): void
	{
		$this->driver->setTransactionIsolationLevel($level);
	}


	/** @inheritdoc */
	public function transactional(callable $callback): mixed
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
			$sql,
		);
	}


	private function createDriver(): IDriver
	{
		if (!isset($this->config['driver'])) {
			throw new InvalidArgumentException('Undefined driver. Choose from: mysqli, pgsql, sqlsrv, pdo_mysql, pdo_pgsql.');
		} elseif ($this->config['driver'] instanceof IDriver) {
			return $this->config['driver'];
		} else {
			$driver = $this->config['driver'];
			$name = ucfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $driver))));
			/** @var class-string<IDriver> $class */
			$class = "Nextras\\Dbal\\Drivers\\{$name}\\{$name}Driver";
			return new $class();
		}
	}


	private function createSqlProcessor(): SqlProcessor
	{
		if (isset($this->config['sqlProcessorFactory'])) {
			$factory = $this->config['sqlProcessorFactory'];
			if (!$factory instanceof ISqlProcessorFactory) {
				throw new InvalidArgumentException("Connection's 'sqlProcessorFactory' configuration key does not contain an instance of " . ISqlProcessorFactory::class . '.');
			}
			return $factory->create($this);
		} else {
			return new SqlProcessor($this->getPlatform());
		}
	}
}
