<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\Exception\ConnectionException;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\NotNullConstraintViolationException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\Drivers\PdoSqlite\PdoSqliteDriver;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../bootstrap.php';


class ExceptionsTest extends IntegrationTestCase
{

	public function testConnection()
	{
		if ($this->connection->getDriver() instanceof PdoSqliteDriver) {
			Environment::skip('Connection cannot fail because wrong configuration.');
		}

		Assert::exception(function () {
			$connection = $this->createConnection(['database' => 'unknown']);
			$connection->connect();
		}, ConnectionException::class);

		Assert::exception(function () {
			$connection = $this->createConnection(['username' => 'unknown']);
			$connection->connect();
		}, ConnectionException::class);
	}


	public function testForeignKeyException()
	{
		Assert::exception(function () {
			$this->initData($this->connection);
			$this->connection->query('UPDATE books SET author_id = 999');
		}, ForeignKeyConstraintViolationException::class);
	}


	public function testUniqueException()
	{
		Assert::exception(function () {
			$this->initData($this->connection);
			$this->connection->query('INSERT INTO publishers %values', ['name' => 'Nextras publisher']);
		}, UniqueConstraintViolationException::class);
	}


	public function testNotNullException()
	{
		Assert::exception(function () {
			$this->initData($this->connection);
			$this->connection->query('UPDATE books SET title = NULL');
		}, NotNullConstraintViolationException::class);
	}


	public function testQueryException()
	{
		/** @var QueryException $e */
		$e = Assert::exception(function () {
			$this->connection->query('SELECT FROM FROM foo');
		}, QueryException::class);

		Assert::same('SELECT FROM FROM foo', $e->getSqlQuery());
	}
}


$test = new ExceptionsTest();
$test->run();
