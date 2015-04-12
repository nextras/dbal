<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\QueryException;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ExceptionsTest extends IntegrationTestCase
{

	public function testConnection()
	{
		Assert::exception(function() {
			$connection = $this->createConnection(['database' => 'unknown']);
			$connection->connect();
		}, 'Nextras\Dbal\ConnectionException');

		Assert::exception(function() {
			$connection = $this->createConnection(['username' => 'unknown']);
			$connection->connect();
		}, 'Nextras\Dbal\ConnectionException');
	}


	public function testForeignKeyException()
	{
		Assert::exception(function() {
			$this->initData($this->connection);
			$this->connection->query('UPDATE books SET author_id = 999');
		}, 'Nextras\Dbal\ForeignKeyConstraintViolationException');
	}


	public function testUniqueException()
	{
		Assert::exception(function() {
			$this->initData($this->connection);
			$this->connection->query('INSERT INTO publishers %values', ['name' => 'Nextras publisher']);
		}, 'Nextras\Dbal\UniqueConstraintViolationException');
	}


	public function testNotNullException()
	{
		Assert::exception(function() {
			$this->initData($this->connection);
			$this->connection->query('UPDATE books SET title = NULL');
		}, 'Nextras\Dbal\NotNullConstraintViolationException');
	}


	public function testQueryException()
	{
		/** @var QueryException $e */
		$e = Assert::exception(function() {
			$this->connection->query('SELECT FROM FROM foo');
		}, 'Nextras\Dbal\QueryException');

		Assert::same('SELECT FROM FROM foo', $e->getSqlQuery());
	}

}


$test = new ExceptionsTest();
$test->run();
