<?php

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Exceptions\InvalidArgumentException;
use Nextras\Dbal\Platforms\PostgrePlatform;
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
		Environment::lock('data', TEMP_DIR);
		if ($connection->getPlatform() instanceof PostgrePlatform) {
			FileImporter::executeFile($connection, __DIR__ . '/../data/pgsql-data.sql');
		} else {
			FileImporter::executeFile($connection, __DIR__ . '/../data/mysql-data.sql');
		}
	}


	protected function createConnection($params = [])
	{
		$options = Environment::loadData() + array('user' => NULL, 'password' => NULL) + $params;
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
