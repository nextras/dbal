<?php

namespace NextrasTests\Dbal;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Exception\InvalidArgumentException;
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
		$platform = $connection->getPlatform()->getName();
		FileImporter::executeFile($connection, __DIR__ . "/../data/$platform-data.sql");
	}


	protected function lockConnection(Connection $connection)
	{
		$key = 'data-' . ($connection->getConfig()['port'] ?? $connection->getPlatform()->getName());
		Environment::lock($key, TEMP_DIR);
	}


	protected function createConnection($params = [])
	{
		$options = array_merge([
			'user' => NULL,
			'password' => NULL,
			'searchPath' => ['public'],
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
