<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Pdo;


use Closure;
use DateTimeZone;
use Exception;
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

	protected PDO|null $connection = null;
	protected DateTimeZone|null $connectionTz = null;
	protected ILogger|null $logger = null;
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


	public function getResourceHandle(): PDO|null
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
		$this->executeTransactionCommand('BEGIN TRANSACTION', static fn(PDO $connection): bool => $connection->beginTransaction());
	}


	public function commitTransaction(): void
	{
		$this->executeTransactionCommand('COMMIT TRANSACTION', static fn(PDO $connection): bool => $connection->commit());
	}


	public function rollbackTransaction(): void
	{
		$this->executeTransactionCommand('ROLLBACK TRANSACTION', static fn(PDO $connection): bool => $connection->rollBack());
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
		string|null $query = null,
		\Throwable|null $previousException = null,
	): Exception;


	/**
	 * @param Closure(PDO): bool $callback
	 */
	private function executeTransactionCommand(string $sql, Closure $callback): void
	{
		$this->checkConnection();
		$connection = $this->connection;
		$logger = $this->logger;
		assert($connection !== null);
		assert($logger !== null);

		$time = microtime(true);
		try {
			$success = $callback($connection);
		} catch (PDOException $e) {
			throw $this->createException(
				$e->getMessage(),
				(int) ($e->errorInfo[1] ?? 0),
				$e->errorInfo[0] ?? (string) $e->getCode(),
				previousException: $e,
			);
		} finally {
			$this->timeTaken = microtime(true) - $time;
		}

		if (!$success) {
			[$sqlState, $errorCode, $errorMsg] = $connection->errorInfo();
			throw $this->createException($errorMsg ?? "$sql failed.", $errorCode ?? 0, $sqlState ?? '');
		}
		$logger->onQuery($sql, $this->timeTaken, null);
	}


	protected function loggedQuery(string $sql): Result
	{
		assert($this->logger !== null);
		return LoggerHelper::loggedQuery($this, $this->logger, $sql);
	}


	protected function checkConnection(): void
	{
		if ($this->connection === null) {
			throw new InvalidStateException('Driver is not connected to database.');
		}
	}
}
