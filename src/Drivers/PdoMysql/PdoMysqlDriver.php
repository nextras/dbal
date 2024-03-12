<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoMysql;


use DateTimeZone;
use Exception;
use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\UnknownMysqlTimezoneException;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\Pdo\PdoDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Platforms\MySqlPlatform;
use Nextras\Dbal\Result\IResultAdapter;
use PDO;
use PDOStatement;
use function array_key_exists;
use function date;
use function date_default_timezone_get;


/**
 * Driver for php_pdo_mysql ext.
 *
 * Supported configuration options:
 * - host - server name to connect;
 * - port - port to connect;
 * - database - db name to connect;
 * - unix_socket - unix socket to connect;
 * - options - int of flags accepted by PDO::connect();
 * - username - username to connect;
 * - password - password to connect;
 * - sslKey - argument of PDO::MYSQL_ATTR_SSL_KEY;
 * - sslCert - argument of PDO::MYSQL_ATTR_SSL_CERT;
 * - sslCa - argument of PDO::MYSQL_ATTR_SSL_CA;
 * - sslCapath - argument of PDO::MYSQL_ATTR_SSL_CAPATH;
 * - sslCipher - argument of PDO::MYSQL_ATTR_SSL_CIPHER;
 * - sslVerify - argument of PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT; since PHP 7.1.4
 * - charset - connection charset; defaults to utf8mb4;
 * - sqlMode - setup of SQL mode; possible values are:
 *    - "TRADITIONAL" - a default one;
 *    - "STRICT_TRANS_TABLES"
 *    - "ANSI"
 *    - and more - see MySQL docs.
 * - connectionTz - timezone for database connection; possible values are:
 *    - "auto"
 *    - "auto-offset"
 *    - specific +-00:00 timezone offset;
 */
class PdoMysqlDriver extends PdoDriver
{
	private ?PdoMysqlResultNormalizerFactory $resultNormalizerFactory = null;


	public function connect(array $params, ILogger $logger): void
	{
		$host = $params['host'] ?? null;
		$unixSocket = $params['unix_socket'] ?? null;
		$port = $params['port'] ?? 3306;
		$username = $params['username'] ?? '';
		$password = $params['password'] ?? '';
		$database = $params['database'] ?? '';
		$charset = $params['charset'] ?? 'utf8mb4';
		$options = (array) ($params['options'] ?? []);

		if (isset($params['sslKey'])) $options[PDO::MYSQL_ATTR_SSL_KEY] = $params['sslKey'];
		if (isset($params['sslCert'])) $options[PDO::MYSQL_ATTR_SSL_CERT] = $params['sslCert'];
		if (isset($params['sslCa'])) $options[PDO::MYSQL_ATTR_SSL_CA] = $params['sslCa'];
		if (isset($params['sslCapath'])) $options[PDO::MYSQL_ATTR_SSL_CAPATH] = $params['sslCapath'];
		if (isset($params['sslCipher'])) $options[PDO::MYSQL_ATTR_SSL_CIPHER] = $params['sslCipher'];
		if (isset($params['sslVerify'])) $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $params['sslVerify'];

		if ($host !== null) {
			$target = "host=$host;";
		} elseif ($unixSocket !== null) {
			$target = "unix_socket=$unixSocket";
		} else {
			throw new InvalidArgumentException();
		}
		$dsn = "mysql:{$target}port=$port;dbname=$database;charset=$charset";

		$this->connectPdo($dsn, $username, $password, $options, $logger);
		$this->resultNormalizerFactory = new PdoMysqlResultNormalizerFactory($this);

		$this->processInitialSettings($params);
	}


	public function createPlatform(IConnection $connection): IPlatform
	{
		return new MySqlPlatform($connection);
	}


	public function getLastInsertedId(string|Fqn|null $sequenceName = null): int
	{
		return (int) parent::getLastInsertedId($sequenceName);
	}


	public function setTransactionIsolationLevel(int $level): void
	{
		static $levels = [
			IConnection::TRANSACTION_READ_UNCOMMITTED => 'READ UNCOMMITTED',
			IConnection::TRANSACTION_READ_COMMITTED => 'READ COMMITTED',
			IConnection::TRANSACTION_REPEATABLE_READ => 'REPEATABLE READ',
			IConnection::TRANSACTION_SERIALIZABLE => 'SERIALIZABLE',
		];
		if (!isset($levels[$level])) {
			throw new NotSupportedException("Unsupported transaction level $level");
		}
		$this->loggedQuery("SET SESSION TRANSACTION ISOLATION LEVEL {$levels[$level]}");
	}


	protected function createResultAdapter(PDOStatement $statement): IResultAdapter
	{
		assert($this->resultNormalizerFactory !== null);
		return (new PdoMysqlResultAdapter($statement, $this->resultNormalizerFactory))->toBuffered();
	}


	protected function convertIdentifierToSql(string|Fqn $identifier): string
	{
		return match (true) {
			$identifier instanceof Fqn => str_replace('`', '``', $identifier->schema) . '.'
				. str_replace('`', '``', $identifier->name),
			default => str_replace('`', '``', $identifier),
		};
	}


	/**
	 * This method is based on Doctrine\DBAL project.
	 * @link www.doctrine-project.org
	 */
	protected function createException(string $error, int $errorNo, string $sqlState, ?string $query = null): Exception
	{
		if (in_array($errorNo, [1216, 1217, 1451, 1452, 1701], true)) {
			return new ForeignKeyConstraintViolationException($error, $errorNo, $sqlState, null, $query);
		} elseif (in_array($errorNo, [1062, 1557, 1569, 1586], true)) {
			return new UniqueConstraintViolationException($error, $errorNo, $sqlState, null, $query);
		} elseif (in_array($errorNo, [1044, 1045, 1046, 1049, 1095, 1142, 1143, 1227, 1370, 2002, 2005, 2054], true)) {
			return new ConnectionException($error, $errorNo, $sqlState);
		} elseif (in_array($errorNo, [1048, 1121, 1138, 1171, 1252, 1263, 1566], true)) {
			return new NotNullConstraintViolationException($error, $errorNo, $sqlState, null, $query);
		} elseif ($errorNo === 1298) {
			return new UnknownMysqlTimezoneException($error, $errorNo, $sqlState, null, $query);
		} elseif ($query !== null) {
			return new QueryException($error, $errorNo, $sqlState, null, $query);
		} else {
			return new DriverException($error, $errorNo, $sqlState);
		}
	}


	/**
	 * @param array<string, mixed> $params
	 */
	protected function processInitialSettings(array $params): void
	{
		if (!array_key_exists('sqlMode', $params)) {
			$params['sqlMode'] = 'TRADITIONAL';
		}
		if ($params['sqlMode'] !== null) {
			$this->loggedQuery('SET sql_mode = ' . $this->convertStringToSql($params['sqlMode']));
		}

		if (!isset($params['connectionTz']) || $params['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_NAME) {
			$params['connectionTz'] = date_default_timezone_get();
		} elseif ($params['connectionTz'] === IDriver::TIMEZONE_AUTO_PHP_OFFSET) {
			$params['connectionTz'] = date('P');
		}

		$this->connectionTz = new DateTimeZone($params['connectionTz']);
		$this->loggedQuery('SET time_zone = ' . $this->convertStringToSql($this->connectionTz->getName()));
	}
}
