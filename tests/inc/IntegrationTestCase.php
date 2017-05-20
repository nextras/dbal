<?php

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Connection;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\InvalidStateException;
use Nextras\Dbal\Platforms\MySqlPlatform;
use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Dbal\Platforms\SqlServerPlatform;
use Nextras\Dbal\Utils\FileImporter;
use Tester\Environment;


/**
 * @property-read Connection $connection
 */
class IntegrationTestCase extends TestCase
{
	/** @var Connection */
	private $defaultConnection;


	public function initData(Connection $connection)
	{
		$this->lockConnection($connection);
		$platform = $connection->getPlatform();
		if ($platform instanceof PostgreSqlPlatform) {
			FileImporter::executeFile($connection, __DIR__ . '/../data/pgsql-data.sql');
		} elseif ($platform instanceof SqlServerPlatform) {
			FileImporter::executeFile($connection, __DIR__ . '/../data/sqlsrv-data.sql');
		} elseif ($platform instanceof MySqlPlatform) {
			FileImporter::executeFile($connection, __DIR__ . '/../data/mysql-data.sql');
		} else {
			throw new InvalidStateException();
		}
	}


	protected function lockConnection(Connection $connection)
	{
		if ($connection->getPlatform() instanceof PostgreSqlPlatform) {
			Environment::lock('data-pgsql', TEMP_DIR);
		} else {
			Environment::lock('data-mysql', TEMP_DIR);
		}
	}


	protected function createConnection($params = [])
	{
		$options = array_merge([
			'user' => NULL,
			'password' => NULL,
			'sqlMode' => 'TRADITIONAL',
			'searchPath' => ['public', 'second_schema'],
		], Environment::loadData(), $params);
		return new Connection($options);
	}


	public function __get($name)
	{
		if ($name === 'connection') {
			if ($this->defaultConnection === NULL) {
				$this->defaultConnection = $this->createConnection();
			}
			return $this->defaultConnection;
		}

		throw new InvalidArgumentException();
	}
}
