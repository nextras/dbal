<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlsrv
 */

namespace NextrasTests\Dbal;

use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class PlatformSqlServerTest extends IntegrationTestCase
{
	public function testTables()
	{
		$tables = $this->connection->getPlatform()->getTables();

		Assert::true(isset($tables["dbo.books"]));
		Assert::same('books', $tables["dbo.books"]->name);
		Assert::same(false, $tables["dbo.books"]->isView);

		Assert::true(isset($tables["dbo.my_books"]));
		Assert::same('my_books', $tables["dbo.my_books"]->name);
		Assert::same(true, $tables["dbo.my_books"]->isView);
	}


	public function testColumns()
	{
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

		$columns = $this->connection->getPlatform()->getColumns('tag_followers');
		$columns = \array_map(function ($column) { return (array) $column; }, $columns);

		Assert::same([
			'tag_id' => [
				'name' => 'tag_id',
				'type' => 'INT',
				'size' => 10,
				'default' => null,
				'isPrimary' => true,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'author_id' => [
				'name' => 'author_id',
				'type' => 'INT',
				'size' => 10,
				'default' => null,
				'isPrimary' => true,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'created_at' => [
				'name' => 'created_at',
				'type' => 'DATETIMEOFFSET',
				'size' => null,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
		], $columns);
	}


	public function testForeignKeys()
	{
		$keys = $this->connection->getPlatform()->getForeignKeys('books');
		$keys = \array_map(function ($key) { return (array) $key; }, $keys);

		Assert::same([
			'author_id' => [
				'name' => 'books_authors',
				'schema' => 'dbo',
				'column' => 'author_id',
				'refTable' => 'authors',
				'refTableSchema' => 'dbo',
				'refColumn' => 'id',
			],
			'ean_id' => [
				'name' => 'books_ean',
				'schema' => 'dbo',
				'column' => 'ean_id',
				'refTable' => 'eans',
				'refTableSchema' => 'dbo',
				'refColumn' => 'id',
			],
			'publisher_id' => [
				'name' => 'books_publisher',
				'schema' => 'dbo',
				'column' => 'publisher_id',
				'refTable' => 'publishers',
				'refTableSchema' => 'dbo',
				'refColumn' => 'id',
			],
			'translator_id' => [
				'name' => 'books_translator',
				'schema' => 'dbo',
				'column' => 'translator_id',
				'refTable' => 'authors',
				'refTableSchema' => 'dbo',
				'refColumn' => 'id',
			],
		], $keys);
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
