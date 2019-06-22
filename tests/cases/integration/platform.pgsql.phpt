<?php declare(strict_types = 1);

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
			'is_view' => false,
			'full_name' => 'public.books',
		], $tables['books']);

		Assert::true(isset($tables['my_books']));
		Assert::same([
			'name' => 'my_books',
			'is_view' => true,
			'full_name' => 'public.my_books',
		], $tables['my_books']);
	}


	public function testColumns()
	{
		$columns = $this->connection->getPlatform()->getColumns('books');
		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INT4',
				'size' => null,
				'default' => "nextval('books_id_seq'::regclass)",
				'is_primary' => true,
				'is_autoincrement' => true,
				'is_unsigned' => false,
				'is_nullable' => false,
				'sequence' => 'books_id_seq',
			],
			'author_id' => [
				'name' => 'author_id',
				'type' => 'INT4',
				'size' => null,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => false,
				'sequence' => null,
			],
			'translator_id' => [
				'name' => 'translator_id',
				'type' => 'INT4',
				'size' => null,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => true,
				'sequence' => null,
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
				'sequence' => null,
			],
			'publisher_id' => [
				'name' => 'publisher_id',
				'type' => 'INT4',
				'size' => null,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => false,
				'sequence' => null,
			],
			'ean_id' => [
				'name' => 'ean_id',
				'type' => 'INT4',
				'size' => null,
				'default' => null,
				'is_primary' => false,
				'is_autoincrement' => false,
				'is_unsigned' => false,
				'is_nullable' => true,
				'sequence' => null,
			],
		], $columns);
	}


	public function testForeignKeys()
	{
		$keys = $this->connection->getPlatform()->getForeignKeys('books');
		Assert::same([
			'author_id' => [
				'name' => 'books_authors',
				'column' => 'author_id',
				'ref_table' => 'second_schema.authors',
				'ref_column' => 'id',
			],
			'translator_id' => [
				'name' => 'books_translator',
				'column' => 'translator_id',
				'ref_table' => 'second_schema.authors',
				'ref_column' => 'id',
			],
			'publisher_id' => [
				'name' => 'books_publisher',
				'column' => 'publisher_id',
				'ref_table' => 'public.publishers',
				'ref_column' => 'id',
			],
			'ean_id' => [
				'name' => 'books_ean',
				'column' => 'ean_id',
				'ref_table' => 'public.eans',
				'ref_column' => 'id',
			],
		], $keys);

		$this->connection->query("DROP TABLE IF EXISTS second_schema.book_fk");
		$this->connection->query("
			CREATE TABLE second_schema.book_fk (
				book_id int NOT NULL,
				CONSTRAINT book_id FOREIGN KEY (book_id) REFERENCES public.books (id) ON DELETE CASCADE ON UPDATE CASCADE
			);
		");

		$schemaKeys = $this->connection->getPlatform()->getForeignKeys('second_schema.book_fk');
		Assert::same([
			'book_id' => [
				'name' => 'book_id',
				'column' => 'book_id',
				'ref_table' => 'public.books',
				'ref_column' => 'id',
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


$test = new PlatformPostgreTest();
$test->run();
