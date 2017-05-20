<?php

namespace NextrasTests\Dbal;

use Nextras\Dbal\Connection;
use Nextras\Dbal\InvalidArgumentException;
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
		Environment::lock('data-' . $connection->getPlatform()->getName(), TEMP_DIR);
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
