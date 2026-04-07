<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoSqlite;


use DateTimeZone;
use Exception;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\Pdo\PdoDriver;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\SqlitePlatform;
use Nextras\Dbal\Result\IResultAdapter;
use PDOStatement;
use function str_replace;
use function strtr;


/**
 * Driver for php_pdo_sqlite ext.
 *
 * Supported configuration options:
 * - filename - file path to database or `:memory:`; defaults to :memory:
 */
class PdoSqliteDriver extends PdoDriver
{
	/** @var PdoSqliteResultNormalizerFactory */
	private $resultNormalizerFactory;


	public function connect(array $params, ILogger $logger): void
	{
		$file = $params['filename'] ?? ':memory:';
		$dsn = "sqlite:$file";
		$this->connectPdo($dsn, '', '', [], $logger);
		$this->resultNormalizerFactory = new PdoSqliteResultNormalizerFactory($this);

		$this->connectionTz = new DateTimeZone('UTC');
		$this->loggedQuery('PRAGMA foreign_keys = 1');
	}


	public function createPlatform(IConnection $connection): IPlatform
	{
		return new SqlitePlatform($connection);
	}


	public function getLastInsertedId(string|Fqn|null $sequenceName = null): mixed
	{
		return $this->query('SELECT last_insert_rowid()')->fetchField();
	}


	public function setTransactionIsolationLevel(int $level): void
	{
		if ($level === Connection::TRANSACTION_READ_UNCOMMITTED) {
			$this->loggedQuery('PRAGMA read_uncommitted = 1');
		} elseif (
			$level === Connection::TRANSACTION_READ_COMMITTED
			|| $level === Connection::TRANSACTION_REPEATABLE_READ
			|| $level === Connection::TRANSACTION_SERIALIZABLE
		) {
			$this->loggedQuery('PRAGMA read_uncommitted = 0');
		} else {
			throw new NotSupportedException("Unsupported transaction level $level");
		}
	}


	protected function createResultAdapter(PDOStatement $statement): IResultAdapter
	{
		return (new PdoSqliteResultAdapter($statement, $this->resultNormalizerFactory))->toBuffered();
	}


	protected function convertIdentifierToSql(string|Fqn $identifier): string
	{
		$escaped = match (true) {
			$identifier instanceof Fqn => str_replace(']', ']]', $identifier->schema) . '.'
				. str_replace(']', ']]', $identifier->name),
			default => str_replace(']', ']]', $identifier),
		};
		return '[' . $escaped . ']';
	}


	protected function createException(string $error, int $errorNo, string $sqlState, ?string $query = null): Exception
	{
		if (stripos($error, 'FOREIGN KEY constraint failed') !== false) {
			return new ForeignKeyConstraintViolationException($error, $errorNo, '', null, $query);
		} elseif (
			strpos($error, 'must be unique') !== false
			|| strpos($error, 'is not unique') !== false
			|| strpos($error, 'are not unique') !== false
			|| strpos($error, 'UNIQUE constraint failed') !== false
		) {
			return new UniqueConstraintViolationException($error, $errorNo, '', null, $query);
		} elseif (
			strpos($error, 'may not be NULL') !== false
			|| strpos($error, 'NOT NULL constraint failed') !== false
		) {
			return new NotNullConstraintViolationException($error, $errorNo, '', null, $query);
		} elseif (stripos($error, 'unable to open database') !== false) {
			return new ConnectionException($error, $errorNo, '');
		} elseif ($query !== null) {
			return new QueryException($error, $errorNo, '', null, $query);
		} else {
			return new DriverException($error, $errorNo, '');
		}
	}
}
