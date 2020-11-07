<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Pdo;


use DateTimeZone;
use Exception;
use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\LoggerHelper;
use Nextras\Dbal\Utils\StrictObjectTrait;
use PDO;
use PDOException;
use PDOStatement;
use function gettype;
use function is_string;


abstract class PdoDriver implements IDriver
{
	use StrictObjectTrait;


	/** @var PDO|null */
	protected $connection;

	/** @var DateTimeZone */
	protected $connectionTz;

	/** @var ILogger */
	protected $logger;

	/** @var float */
	protected $timeTaken = 0.0;

	/** @var int */
	protected $affectedRows = 0;


	/**
	 * @param array<int, mixed> $options
	 */
	protected function connectPdo(
		string $dsn,
		string $username,
		string $password,
		array $options,
		ILogger $logger
	): void
	{
		$this->logger = $logger;

		try {
			$connection = new PDO($dsn, $username, $password, $options);
			$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		} catch (PDOException $e) {
			throw new ConnectionException($e->getMessage(), $e->getCode(), '', $e);
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


	/**
	 * @return PDO|null
	 */
	public function getResourceHandle()
	{
		return $this->connection;
	}


	public function getConnectionTimeZone(): DateTimeZone
	{
		return $this->connectionTz;
	}


	public function query(string $query): Result
	{
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
		return new Result($resultAdapter, $this);
	}


	public function getLastInsertedId(?string $sequenceName = null)
	{
		assert($this->connection !== null);
		return $this->connection->lastInsertId($sequenceName); // @phpstan-ignore-line
	}


	public function getAffectedRows(): int
	{
		assert($this->connection !== null);
		return $this->affectedRows;
	}


	public function getQueryElapsedTime(): float
	{
		return $this->timeTaken;
	}


	public function getServerVersion(): string
	{
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
		assert($this->connection !== null);
		$this->connection->query('SELECT 1');
		return $this->connection->errorCode() === '00000';
	}


	public function beginTransaction(): void
	{
		assert($this->connection !== null);

		try {
			$time = microtime(true);
			$this->connection->beginTransaction();
		} catch (PDOException $e) {
			throw new DriverException($e->getMessage(), $e->getCode(), '', $e);
		}

		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('BEGIN TRANSACTION', $timeTaken, null);
	}


	public function commitTransaction(): void
	{
		assert($this->connection !== null);

		try {
			$time = microtime(true);

			$this->connection->commit();
		} catch (PDOException $e) {
			throw new DriverException($e->getMessage(), $e->getCode(), '', $e);
		}

		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('COMMIT TRANSACTION', $timeTaken, null);
	}


	public function rollbackTransaction(): void
	{
		assert($this->connection !== null);

		try {
			$time = microtime(true);
			$this->connection->rollBack();
		} catch (PDOException $e) {
			throw new DriverException($e->getMessage(), $e->getCode(), '', $e);
		}

		$timeTaken = microtime(true) - $time;
		$this->logger->onQuery('ROLLBACK TRANSACTION', $timeTaken, null);
	}


	public function createSavepoint(string $name): void
	{
		$identifier = $this->convertIdentifierToSql($name);
		$this->loggedQuery("SAVEPOINT $identifier");
	}


	public function releaseSavepoint(string $name): void
	{
		$identifier = $this->convertIdentifierToSql($name);
		$this->loggedQuery("RELEASE SAVEPOINT $identifier");
	}


	public function rollbackSavepoint(string $name): void
	{
		$identifier = $this->convertIdentifierToSql($name);
		$this->loggedQuery("ROLLBACK TO SAVEPOINT $identifier");
	}


	public function convertToPhp($value, $nativeType)
	{
		throw new NotSupportedException("Abstract PdoDriver does not support '{$nativeType}' type conversion.");
	}


	public function convertStringToSql(string $value): string
	{
		assert($this->connection !== null);
		return $this->connection->quote($value); // @phpstan-ignore-line
	}


	/**
	 * @param PDOStatement<mixed> $statement
	 */
	abstract protected function createResultAdapter(PDOStatement $statement): IResultAdapter;


	abstract protected function convertIdentifierToSql(string $identifier): string;


	abstract protected function createException(
		string $error,
		int $errorNo,
		string $sqlState,
		?string $query = null
	): Exception;


	protected function loggedQuery(string $sql): Result
	{
		return LoggerHelper::loggedQuery($this, $this->logger, $sql);
	}
}
