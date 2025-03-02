<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlsrv
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Platforms\Data\Fqn;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class PlatformSqlServerTest extends IntegrationTestCase
{
	public function testTables()
	{
		$this->lockConnection($this->connection);
		$tables = $this->connection->getPlatform()->getTables();

		Assert::true(isset($tables["dbo.books"]));
		Assert::same('books', $tables["dbo.books"]->fqnName->name);
		Assert::same(false, $tables["dbo.books"]->isView);

		Assert::true(isset($tables["dbo.my_books"]));
		Assert::same('my_books', $tables["dbo.my_books"]->fqnName->name);
		Assert::same(true, $tables["dbo.my_books"]->isView);

		$tables = $this->connection->getPlatform()->getTables('second_schema');
		Assert::true(isset($tables['second_schema.authors']));
		Assert::same('authors', $tables['second_schema.authors']->fqnName->name);
		Assert::same(false, $tables['second_schema.authors']->isView);
	}


	public function testColumns()
	{
		$this->lockConnection($this->connection);
		$columns = $this->connection->getPlatform()->getColumns('books');
		$columns = \array_map(function ($column) { return (array) $column; }, $columns);

		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INT',
				'size' => 10,
				'default' => null,
				'isPrimary' => true,
				'isAutoincrement' => true,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'author_id' => [
				'name' => 'author_id',
				'type' => 'INT',
				'size' => 10,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'translator_id' => [
				'name' => 'translator_id',
				'type' => 'INT',
				'size' => 10,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => true,
				'meta' => [],
			],
			'title' => [
				'name' => 'title',
				'type' => 'VARCHAR',
				'size' => 50,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'publisher_id' => [
				'name' => 'publisher_id',
				'type' => 'INT',
				'size' => 10,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'ean_id' => [
				'name' => 'ean_id',
				'type' => 'INT',
				'size' => 10,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => true,
				'meta' => [],
			],
		], $columns);

		$schemaColumns = $this->connection->getPlatform()->getColumns('authors', 'second_schema');
		$schemaColumns = \array_map(function ($column) { return (array) $column; }, $schemaColumns);

		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INT',
				'size' => 10,
				'default' => null,
				'isPrimary' => true,
				'isAutoincrement' => true,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'name' => [
				'name' => 'name',
				'type' => 'VARCHAR',
				'size' => 50,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'web' => [
				'name' => 'web',
				'type' => 'VARCHAR',
				'size' => 100,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'born' => [
				'name' => 'born',
				'type' => 'DATE',
				'size' => null,
				'default' => '(NULL)',
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => true,
				'meta' => [],
			],
		], $schemaColumns);
	}


	public function testForeignKeys()
	{
		$this->lockConnection($this->connection);

		$keys = $this->connection->getPlatform()->getForeignKeys('books');
		$keys = \array_map(function ($key) { return (array) $key; }, $keys);

		Assert::equal([
			'author_id' => [
				'fqnName' => new Fqn('dbo', 'books_authors'),
				'column' => 'author_id',
				'refTable' => new Fqn('second_schema', 'authors'),
				'refColumn' => 'id',
			],
			'ean_id' => [
				'fqnName' => new Fqn('dbo', 'books_ean'),
				'column' => 'ean_id',
				'refTable' => new Fqn('dbo', 'eans'),
				'refColumn' => 'id',
			],
			'publisher_id' => [
				'fqnName' => new Fqn('dbo', 'books_publisher'),
				'column' => 'publisher_id',
				'refTable' => new Fqn('dbo', 'publishers'),
				'refColumn' => 'id',
			],
			'translator_id' => [
				'fqnName' => new Fqn('dbo', 'books_translator'),
				'column' => 'translator_id',
				'refTable' => new Fqn('second_schema', 'authors'),
				'refColumn' => 'id',
			],
		], $keys);

		$this->connection->query("DROP TABLE IF EXISTS second_schema.book_fk");
		$this->connection->query("
			CREATE TABLE second_schema.book_fk (
				book_id int NOT NULL,
				CONSTRAINT book_id FOREIGN KEY (book_id) REFERENCES dbo.books (id) ON DELETE CASCADE ON UPDATE CASCADE
			);
		");

		$schemaKeys = $this->connection->getPlatform()->getForeignKeys('book_fk', 'second_schema');
		$schemaKeys = \array_map(function ($key) { return (array) $key; }, $schemaKeys);

		Assert::equal([
			'book_id' => [
				'fqnName' => new Fqn('second_schema', 'book_id'),
				'column' => 'book_id',
				'refTable' => new Fqn('dbo', 'books'),
				'refColumn' => 'id',
			],
		], $schemaKeys);
	}


	public function testPrimarySequence()
	{
		Assert::same(null, $this->connection->getPlatform()->getPrimarySequenceName('books'));
	}


	public function testName()
	{
		Assert::same('mssql', $this->connection->getPlatform()->getName());
	}
}


$test = new PlatformSqlServerTest();
$test->run();
