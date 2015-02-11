<?php

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
			'is_view' => FALSE,
		], $tables['books']);

		Assert::true(isset($tables['my_books']));
		Assert::same([
			'name' => 'my_books',
			'is_view' => TRUE,
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
				'default' => NULL,
				'is_primary' => TRUE,
				'is_autoincrement' => TRUE,
				'is_unsigned' => FALSE,
				'is_nullable' => FALSE,
			],
			'author_id' => [
				'name' => 'author_id',
				'type' => 'INT',
				'size' => 11,
				'default' => NULL,
				'is_primary' => FALSE,
				'is_autoincrement' => FALSE,
				'is_unsigned' => FALSE,
				'is_nullable' => FALSE,
			],
			'translator_id' => [
				'name'=> 'translator_id',
				'type' => 'INT',
				'size'=> 11,
				'default' => NULL,
				'is_primary' => FALSE,
				'is_autoincrement' => FALSE,
				'is_unsigned' => FALSE,
				'is_nullable' => TRUE,
			],
			'title' => [
				'name' => 'title',
				'type' => 'VARCHAR',
				'size' => 50,
				'default' => NULL,
				'is_primary' => FALSE,
				'is_autoincrement' => FALSE,
				'is_unsigned' => FALSE,
				'is_nullable' => FALSE,
			],
			'publisher_id' => [
				'name'=> 'publisher_id',
				'type' => 'INT',
				'size' => 11,
				'default' => NULL,
				'is_primary' => FALSE,
				'is_autoincrement' => FALSE,
				'is_unsigned' => FALSE,
				'is_nullable' => FALSE,
			],
			'ean_id' => [
				'name' => 'ean_id',
				'type' => 'INT',
				'size' => 11,
				'default' => NULL,
				'is_primary' => FALSE,
				'is_autoincrement' => FALSE,
				'is_unsigned' => FALSE,
				'is_nullable' => TRUE,
			],
		], $columns);
	}


	public function testFoeignKeys()
	{
		$keys = $this->connection->getPlatform()->getForeignKeys('books');
		Assert::same([
			'author_id' => [
				'name' => 'books_authors',
				'column' => 'author_id',
				'ref_table' => 'authors',
				'ref_column' => 'id',
			],
			'translator_id' => [
      			'name' => 'books_translator',
				'column' => 'translator_id',
				'ref_table' => 'authors',
				'ref_column' => 'id',
			],
			'publisher_id' => [
				'name' => 'books_publisher',
				'column' => 'publisher_id',
				'ref_table' => 'publishers',
				'ref_column' => 'id',
			],
			'ean_id' => [
				'name' => 'books_ean',
				'column' => 'ean_id',
				'ref_table' => 'eans',
				'ref_column' => 'id',
			]
		], $keys);
	}

}


$test = new PlatformMysqlTest();
$test->run();
