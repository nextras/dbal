<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Platforms\Data\Fqn;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class PlatformPostgresTest extends IntegrationTestCase
{
	public function testTables()
	{
		$tables = $this->connection->getPlatform()->getTables();

		Assert::true(isset($tables["public.books"]));
		Assert::same('books', $tables["public.books"]->name);
		Assert::same(false, $tables["public.books"]->isView);

		Assert::true(isset($tables["public.my_books"]));
		Assert::same('my_books', $tables["public.my_books"]->name);
		Assert::same(true, $tables["public.my_books"]->isView);

		$tables = $this->connection->getPlatform()->getTables('second_schema');
		Assert::true(isset($tables['second_schema.authors']));
		Assert::same('authors', $tables['second_schema.authors']->name);
		Assert::same(false, $tables['second_schema.authors']->isView);
	}


	public function testColumns()
	{
		$columns = $this->connection->getPlatform()->getColumns('books');
		$columns = \array_map(function ($column) { return (array) $column; }, $columns);

		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INT4',
				'size' => null,
				'default' => "nextval('books_id_seq'::regclass)",
				'isPrimary' => true,
				'isAutoincrement' => true,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => ['sequence' => 'books_id_seq'],
			],
			'author_id' => [
				'name' => 'author_id',
				'type' => 'INT4',
				'size' => null,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'translator_id' => [
				'name' => 'translator_id',
				'type' => 'INT4',
				'size' => null,
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
				'type' => 'INT4',
				'size' => null,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'ean_id' => [
				'name' => 'ean_id',
				'type' => 'INT4',
				'size' => null,
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
				'type' => 'INT4',
				'size' => null,
				'default' => "nextval('second_schema.authors_id_seq'::regclass)",
				'isPrimary' => true,
				'isAutoincrement' => true,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => ['sequence' => 'second_schema.authors_id_seq'],
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
				'default' => null,
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
		$keys = $this->connection->getPlatform()->getForeignKeys('books');
		$keys = \array_map(function ($key) { return (array) $key; }, $keys);

		Assert::equal([
			'author_id' => [
				'fqnName' => new Fqn('books_authors', 'public'),
				'column' => 'author_id',
				'refTable' => new Fqn('authors', 'second_schema'),
				'refColumn' => 'id',
			],
			'translator_id' => [
				'fqnName' => new Fqn('books_translator', 'public'),
				'column' => 'translator_id',
				'refTable' => new Fqn('authors', 'second_schema'),
				'refColumn' => 'id',
			],
			'publisher_id' => [
				'fqnName' => new Fqn('books_publisher', 'public'),
				'column' => 'publisher_id',
				'refTable' => new Fqn('publishers', 'public'),
				'refColumn' => 'id',
			],
			'ean_id' => [
				'fqnName' => new Fqn('books_ean', 'public'),
				'column' => 'ean_id',
				'refTable' => new Fqn('eans', 'public'),
				'refColumn' => 'id',
			],
		], $keys);

		$this->connection->query("DROP TABLE IF EXISTS second_schema.book_fk");
		$this->connection->query("
			CREATE TABLE second_schema.book_fk (
				book_id int NOT NULL,
				CONSTRAINT book_id FOREIGN KEY (book_id) REFERENCES public.books (id) ON DELETE CASCADE ON UPDATE CASCADE
			);
		");

		$schemaKeys = $this->connection->getPlatform()->getForeignKeys('book_fk', 'second_schema');
		$schemaKeys = \array_map(function ($key) { return (array) $key; }, $schemaKeys);

		Assert::equal([
			'book_id' => [
				'fqnName' => new Fqn('book_id', 'second_schema'),
				'column' => 'book_id',
				'refTable' => new Fqn('books', 'public'),
				'refColumn' => 'id',
			],
		], $schemaKeys);
	}


	public function testPrimarySequence()
	{
		Assert::same('books_id_seq', $this->connection->getPlatform()->getPrimarySequenceName('books'));
	}


	public function testName()
	{
		Assert::same('pgsql', $this->connection->getPlatform()->getName());
	}
}


$test = new PlatformPostgresTest();
$test->run();
