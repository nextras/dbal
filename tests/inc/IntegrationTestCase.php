<?php

namespace NextrasTests\Dbal;


use Nextras\Dbal\Connection;
use Nextras\Dbal\Exception\InvalidArgumentException;
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
		$platformName = $platform->getName();
		$parser = $platform->createMultiQueryParser();
		foreach ($parser->parseFile(__DIR__ . "/../data/$platformName-data.sql") as $sql) {
			$connection->query('%raw', $sql);
		}
	}


	protected function lockConnection(Connection $connection)
	{
		$key = 'data-' . ($connection->getConfig()['port'] ?? $connection->getPlatform()->getName());
		Environment::lock($key, TEMP_DIR);
	}


	protected function createConnection($params = [])
	{
		$options = array_merge([
			'user' => null,
			'password' => null,
			'searchPath' => ['public'],
			'sqlProcessorFactory' => new SqlProcessorFactory(),
		], Environment::loadData(), $params);
		return new Connection($options);
	}


	public function __get($name)
	{
		if ($name === 'connection') {
			if ($this->defaultConnection === null) {
				$this->defaultConnection = $this->createConnection();
			}
			return $this->defaultConnection;
		}

		throw new InvalidArgumentException();
	}
}
