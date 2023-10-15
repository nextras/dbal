<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Pdo;


use DateTimeZone;
use Exception;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\LoggerHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;
use PDO;
use PDOException;
use PDOStatement;
use function gettype;
use function is_string;
use function preg_match;


abstract class PdoDriver implements IDriver
{
	use StrictObjectTrait;


	protected ?PDO $connection = null;
	protected ?DateTimeZone $connectionTz = null;
	protected ?ILogger $logger = null;
	protected float $timeTaken = 0.0;
	protected int $affectedRows = 0;


	/**
	 * @param array<int, mixed> $options
	 */
	protected function connectPdo(
		string $dsn,
		string $username,
		string $password,
		array $options,
		ILogger $logger,
	): void
	{
		$this->logger = $logger;

		try {
			$connection = new PDO($dsn, $username, $password, $options);
			$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		} catch (PDOException $e) {
			if (preg_match('~SQLSTATE\[([^]]+)]~', $e->getMessage(), $matches) === 1) {
				$sqlState = $matches[1];
			} else {
				$sqlState = '';
			}
			throw $this->createException($e->getMessage(), (int) $e->getCode(), $sqlState);
		}

		$this->connection = $connection;
	}


	public function disconnect(): void
	{
		$this->connection = null;
	}


	public function isConnected(): bool
	{
		return $this->connection !== null;
	}


	public function getResourceHandle(): ?PDO
	{
		return $this->connection;
	}


	public function getConnectionTimeZone(): DateTimeZone
	{
		$this->checkConnection();
		assert($this->connectionTz !== null);
		return $this->connectionTz;
	}


	public function query(string $query): Result
	{
		$this->checkConnection();
		assert($this->connection !== null);

		$time = microtime(true);
		$result = $this->connection->query($query);
		$this->timeTaken = microtime(true) - $time;

		if ($result === false) {
			[$sqlState, $errorCode, $errorMsg] = $this->connection->errorInfo();
			throw $this->createException($errorMsg, $errorCode, $sqlState, $query);
		}

		$this->affectedRows = $result->rowCount();
		$resultAdapter = $this->createResultAdapter($result);
		return new Result($resultAdapter);
	}


	public function getLastInsertedId(string|Fqn|null $sequenceName = null): mixed
	{
		$this->checkConnection();
		assert($this->connection !== null);
		return $this->connection->lastInsertId($sequenceName); // @phpstan-ignore-line
	}


	public function getAffectedRows(): int
	{
		$this->checkConnection();
		assert($this->connection !== null);
		return $this->affectedRows;
	}


	public function getQueryElapsedTime(): float
	{
		return $this->timeTaken;
	}


	public function getServerVersion(): string
	{
		$this->checkConnection();
		assert($this->connection !== null);
		$serverVersion = $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
		if (!is_string($serverVersion)) {
			$actual = gettype($serverVersion);
			throw new InvalidStateException("Server version has to be string, $actual returned.");
		}
		return $serverVersion;
	}


	public function ping(): bool
	{
		$this->checkConnection();
		assert($this->connection !== null);
		$this->connection->query('SELECT 1');
		return $this->connection->errorCode() === '00000';
	}


	public function beginTransaction(): void
	{
		$this->checkConnection();
		assert($this->connection !== null);
		assert($this->logger !== null);

		try {
			$time = microtime(true);
			$this->connection->beginTransaction();
		} catch (PDOException $e) {
			throw new DriverException($e->getMessage(), 0, $e->getCode(), $e);
		}

		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('BEGIN TRANSACTION', $timeTaken, null);
	}


	public function commitTransaction(): void
	{
		$this->checkConnection();
		assert($this->connection !== null);
		assert($this->logger !== null);

		try {
			$time = microtime(true);

			$this->connection->commit();
		} catch (PDOException $e) {
			throw new DriverException($e->getMessage(), 0, $e->getCode(), $e);
		}

		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('COMMIT TRANSACTION', $timeTaken, null);
	}


	public function rollbackTransaction(): void
	{
		$this->checkConnection();
		assert($this->connection !== null);
		assert($this->logger !== null);

		try {
			$time = microtime(true);
			$this->connection->rollBack();
		} catch (PDOException $e) {
			throw new DriverException($e->getMessage(), 0, $e->getCode(), $e);
		}

		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('ROLLBACK TRANSACTION', $timeTaken, null);
	}


	public function createSavepoint(string|Fqn $name): void
	{
		$this->checkConnection();
		$identifier = $this->convertIdentifierToSql($name);
		$this->loggedQuery("SAVEPOINT $identifier");
	}


	public function releaseSavepoint(string|Fqn $name): void
	{
		$this->checkConnection();
		$identifier = $this->convertIdentifierToSql($name);
		$this->loggedQuery("RELEASE SAVEPOINT $identifier");
	}


	public function rollbackSavepoint(string|Fqn $name): void
	{
		$this->checkConnection();
		$identifier = $this->convertIdentifierToSql($name);
		$this->loggedQuery("ROLLBACK TO SAVEPOINT $identifier");
	}


	public function convertStringToSql(string $value): string
	{
		$this->checkConnection();
		assert($this->connection !== null);
		return $this->connection->quote($value); // @phpstan-ignore-line
	}


	/**
	 * @param PDOStatement<mixed> $statement
	 */
	abstract protected function createResultAdapter(PDOStatement $statement): IResultAdapter;


	abstract protected function convertIdentifierToSql(string|Fqn $identifier): string;


	abstract protected function createException(
		string $error,
		int $errorNo,
		string $sqlState,
		?string $query = null,
	): Exception;


	protected function loggedQuery(string $sql): Result
	{
		assert($this->logger !== null);
		return LoggerHelper::loggedQuery($this, $this->logger, $sql);
	}


	protected function checkConnection(): void
	{
		if ($this->connection === null) {
			throw new InvalidStateException("Driver is not connected to database.");
		}
	}
}
