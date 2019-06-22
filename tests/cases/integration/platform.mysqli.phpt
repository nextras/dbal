<?php declare(strict_types=1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;

use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class PlatformMysqlTest extends IntegrationTestCase
{
	public function testTables()
	{
		$tables = $this->connection->getPlatform()->getTables();

		Assert::true(isset($tables['books']));
		Assert::same([
			'name' => 'books',
			'is_view' => false,
		], $tables['books']);

		Assert::true(isset($tables['my_books']));
		Assert::same([
			'name' => 'my_books',
			'is_view' => true,
		], $tables['my_books']);
	}


	public function testColumns()
	{
		$columns = $this->connection->getPlatform()->getColumns('books');
		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INT',
				'size' => 11,
				'default' => null,
				'is_primary' => true,
				'is_autoincrement' => true,
				'is_unsigned' => false,
				'is_nullable' => false,
			],
			'author_id' => [
				'name' => 'author_id',
				'type' => 'INT',
				'size' => 11,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => false,
			],
			'translator_id' => [
				'name' => 'translator_id',
				'type' => 'INT',
				'size' => 11,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => true,
			],
			'title' => [
				'name' => 'title',
				'type' => 'VARCHAR',
				'size' => 50,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => false,
			],
			'publisher_id' => [
				'name' => 'publisher_id',
				'type' => 'INT',
				'size' => 11,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => false,
			],
			'ean_id' => [
				'name' => 'ean_id',
				'type' => 'INT',
				'size' => 11,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => true,
			],
		], $columns);

		$dbName2 = $this->connection->getConfig()['database'] . '2';
		$this->connection->query("DROP TABLE IF EXISTS $dbName2.book_cols");
		$this->connection->query("
			CREATE TABLE $dbName2.book_cols (
				book_id int NOT NULL
			);
		");

		$columns = $this->connection->getPlatform()->getColumns("$dbName2.book_cols");
		Assert::same([
			'book_id' => [
				'name' => 'book_id',
				'type' => 'INT',
				'size' => 11,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => false,
			],
		], $columns);
	}


	public function testForeignKeys()
	{
		$dbName = $this->connection->getConfig()['database'];
		$keys = $this->connection->getPlatform()->getForeignKeys('books');
		Assert::same([
			'author_id' => [
				'name' => 'books_authors',
				'column' => 'author_id',
				'ref_table' => 'authors',
				'ref_table_fqn' => "$dbName.authors",
				'ref_column' => 'id',
			],
			'ean_id' => [
				'name' => 'books_ean',
				'column' => 'ean_id',
				'ref_table' => 'eans',
				'ref_table_fqn' => "$dbName.eans",
				'ref_column' => 'id',
			],
			'publisher_id' => [
				'name' => 'books_publisher',
				'column' => 'publisher_id',
				'ref_table' => 'publishers',
				'ref_table_fqn' => "$dbName.publishers",
				'ref_column' => 'id',
			],
			'translator_id' => [
				'name' => 'books_translator',
				'column' => 'translator_id',
				'ref_table' => 'authors',
				'ref_table_fqn' => "$dbName.authors",
				'ref_column' => 'id',
			],
		], $keys);

		$dbName2 = $this->connection->getConfig()['database'] . '2';
		$this->connection->query("DROP TABLE IF EXISTS $dbName2.book_fk");
		$this->connection->query("
			CREATE TABLE $dbName2.book_fk (
				book_id int NOT NULL,
				CONSTRAINT book_id FOREIGN KEY (book_id) REFERENCES $dbName.books (id) ON DELETE CASCADE ON UPDATE CASCADE
			);
		");
		$keys = $this->connection->getPlatform()->getForeignKeys("$dbName2.book_fk");
		Assert::same([
			'book_id' => [
				'name' => 'book_id',
				'column' => 'book_id',
				'ref_table' => 'books',
				'ref_table_fqn' => "$dbName.books",
				'ref_column' => 'id',
			],
		], $keys);
	}


	public function testPrimarySequence()
	{
		Assert::null($this->connection->getPlatform()->getPrimarySequenceName('books'));
	}


	public function testName()
	{
		Assert::same('mysql', $this->connection->getPlatform()->getName());
	}
}


$test = new PlatformMysqlTest();
$test->run();
