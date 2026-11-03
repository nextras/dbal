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
use function spl_object_id;
use function str_replace;
use function ucfirst;
use function ucwords;


class Connection implements IConnection
{
	use StrictObjectTrait;

	private IDriver $driver;
	private IPlatform|null $platform = null;
	private SqlProcessor $sqlPreprocessor;
	private bool $connected;
	private int $nestedTransactionIndex = 0;
	private bool $nestedTransactionsWithSavepoint = true;
	private MultiLogger $logger;


	public function __construct(
		/** @var array<string, mixed> see drivers for supported options */
		private array $config,
	)
	{
		$this->driver = $this->createDriver();
		$this->sqlPreprocessor = $this->createSqlProcessor();
		$this->connected = $this->driver->isConnected();
		$this->logger = new MultiLogger();
	}


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


	public function disconnect(): void
	{
		if (!$this->connected) {
			return;
		}
		$this->driver->disconnect();
		$this->connected = false;
		$this->logger->onDisconnect();
	}


	public function reconnect(): void
	{
		$this->disconnect();
		$this->connect();
	}


	/** @inheritDoc */
	public function reconnectWithConfig(array $config): void
	{
		$this->disconnect();
		$this->config = $config + $this->config;
		$this->driver = $this->createDriver();
		$this->sqlPreprocessor = $this->createSqlProcessor();
		$this->connect();
	}


	#[\NoDiscard]
	public function getDriver(): IDriver
	{
		return $this->driver;
	}


	/** @inheritDoc */
	#[\NoDiscard]
	public function getConfig(): array
	{
		return $this->config;
	}


	public function query(string $expression, mixed ...$args): Result
	{
		if (!$this->connected) {
			$this->connect();
		}
		array_unshift($args, $expression);
		$sql = $this->sqlPreprocessor->process($args);
		return $this->nativeQuery($sql);
	}


	/** @inheritDoc */
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


	/** @inheritDoc */
	#[\NoDiscard]
	public function getLastInsertedId(string|Fqn|null $sequenceName = null)
	{
		if (!$this->connected) {
			$this->connect();
		}
		return $this->driver->getLastInsertedId($sequenceName);
	}


	#[\NoDiscard]
	public function getAffectedRows(): int
	{
		if (!$this->connected) {
			$this->connect();
		}
		return $this->driver->getAffectedRows();
	}


	#[\NoDiscard]
	public function getPlatform(): IPlatform
	{
		if ($this->platform === null) {
			$this->platform = $this->driver->createPlatform($this);
		}

		return $this->platform;
	}


	#[\NoDiscard]
	public function createQueryBuilder(): QueryBuilder
	{
		return new QueryBuilder($this->getPlatform());
	}


	public function setTransactionIsolationLevel(int $level): void
	{
		$this->driver->setTransactionIsolationLevel($level);
	}


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


	#[\NoDiscard]
	public function getTransactionNestedIndex(): int
	{
		return $this->nestedTransactionIndex;
	}


	public function createSavepoint(string $name): void
	{
		if (!$this->connected) {
			$this->connect();
		}
		$this->driver->createSavepoint($name);
	}


	public function releaseSavepoint(string $name): void
	{
		if (!$this->connected) {
			$this->connect();
		}
		$this->driver->releaseSavepoint($name);
	}


	public function rollbackSavepoint(string $name): void
	{
		if (!$this->connected) {
			$this->connect();
		}
		$this->driver->rollbackSavepoint($name);
	}


	public function ping(): bool
	{
		if (!$this->connected) {
			return false;
		}
		return $this->driver->ping();
	}


	public function addLogger(ILogger $logger): void
	{
		$this->logger->loggers[spl_object_id($logger)] = $logger;
	}


	public function removeLogger(ILogger $logger): void
	{
		unset($this->logger->loggers[spl_object_id($logger)]);
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
