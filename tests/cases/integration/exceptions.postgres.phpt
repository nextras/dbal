<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ExceptionsPostgresTest extends IntegrationTestCase
{

	public function testForeignKeyRestrictException()
	{
		$this->lockConnection($this->connection);

		$this->connection->query('DROP TABLE IF EXISTS dbal_restrict_children');
		$this->connection->query('DROP TABLE IF EXISTS dbal_restrict_parents');
		$this->connection->query('CREATE TABLE dbal_restrict_parents (id INT PRIMARY KEY)');
		$this->connection->query('
			CREATE TABLE dbal_restrict_children (
				parent_id INT NOT NULL REFERENCES dbal_restrict_parents (id) ON DELETE RESTRICT
			)
		');
		$this->connection->query('INSERT INTO dbal_restrict_parents (id) VALUES (1)');
		$this->connection->query('INSERT INTO dbal_restrict_children (parent_id) VALUES (1)');

		try {
			Assert::exception(function () {
				$this->connection->query('DELETE FROM dbal_restrict_parents WHERE id = 1');
			}, ForeignKeyConstraintViolationException::class);
		} finally {
			$this->connection->query('DROP TABLE IF EXISTS dbal_restrict_children');
			$this->connection->query('DROP TABLE IF EXISTS dbal_restrict_parents');
		}
	}
}


$test = new ExceptionsPostgresTest();
$test->run();
