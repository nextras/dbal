<?php

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Exceptions\InvalidArgumentException;
use Tester\Environment;


/**
 * @property-read Connection $connection
 */
class IntegrationTestCase extends TestCase
{
	/** @var Connection */
	private $defaultConnection;


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
