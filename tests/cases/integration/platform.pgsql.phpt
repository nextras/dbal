<?php

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;

use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class PlatformPostgreTest extends IntegrationTestCase
{

	public function testTables()
	{
		$tables = $this->connection->getPlatform()->getTables();

		Assert::true(isset($tables['books']));
		Assert::same([
			'name' => 'books',
			'is_view' => FALSE,
			'full_name' => 'public.books',
		], $tables['books']);

		Assert::true(isset($tables['my_books']));
		Assert::same([
			'name' => 'my_books',
			'is_view' => TRUE,
			'full_name' => 'public.my_books',
		], $tables['my_books']);
	}


	public function testColumns()
	{
		$columns = $this->connection->getPlatform()->getColumns('books');
		Assert::same([
			 'id' => [
				'name'=> 'id',
				'type' => 'INT4',
				'size'=> NULL,
				'default'=> "nextval('books_id_seq'::regclass)",
				'is_primary'=> TRUE,
				'is_autoincrement' => TRUE,
				'is_unsigned'=> FALSE,
				'is_nullable' => FALSE,
				'sequence'=> 'books_id_seq',
			],
			'author_id' => [
				'name' => 'author_id',
				'type'=> 'INT4',
				'size' => NULL,
				'default'=> NULL,
				'is_primary' => FALSE,
				'is_autoincrement'=> FALSE,
				'is_unsigned' => FALSE,
				'is_nullable'=> FALSE,
				'sequence' => NULL,
			],
			'translator_id' => [
				'name'=> 'translator_id',
				'type' => 'INT4',
				'size'=> NULL,
				'default' => NULL,
				'is_primary'=> FALSE,
				'is_autoincrement' => FALSE,
				'is_unsigned'=> FALSE,
				'is_nullable' => TRUE,
				'sequence'=> NULL,
			],
			'title' => [
				'name' => 'title',
				'type'=> 'VARCHAR',
				'size' => 50,
				'default'=> NULL,
				'is_primary' => FALSE,
				'is_autoincrement'=> FALSE,
				'is_unsigned' => FALSE,
				'is_nullable'=> FALSE,
				'sequence' => NULL,
			],
			'publisher_id' => [
				'name'=> 'publisher_id',
				'type' => 'INT4',
				'size'=> NULL,
				'default' => NULL,
				'is_primary'=> FALSE,
				'is_autoincrement' => FALSE,
				'is_unsigned'=> FALSE,
				'is_nullable' => FALSE,
				'sequence'=> NULL,
			],
			'ean_id' => [
				'name' => 'ean_id',
				'type'=> 'INT4',
				'size' => NULL,
				'default'=> NULL,
				'is_primary' => FALSE,
				'is_autoincrement'=> FALSE,
				'is_unsigned' => FALSE,
				'is_nullable'=> TRUE,
				'sequence' => NULL,
			]
		], $columns);
	}


	public function testForeignKeys()
	{
		$keys = $this->connection->getPlatform()->getForeignKeys('books');
		Assert::same([
			'author_id' => [
				'name' => 'books_authors',
				'column' => 'author_id',
				'ref_table' => 'public.authors' ,
				'ref_column' => 'id',
			],
			'translator_id' => [
				'name' => 'books_translator' ,
				'column' => 'translator_id' ,
				'ref_table' => 'public.authors' ,
				'ref_column' => 'id',
			],
			'publisher_id' => [
				'name' => 'books_publisher' ,
				'column' => 'publisher_id' ,
				'ref_table' => 'public.publishers' ,
				'ref_column' => 'id',
			],
			'ean_id' => [
				'name' => 'books_ean',
				'column' => 'ean_id',
				'ref_table' => 'public.eans' ,
				'ref_column' => 'id',
			]
		], $keys);
	}

}


$test = new PlatformPostgreTest();
$test->run();
