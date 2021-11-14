<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini sqlite
 */

namespace NextrasTests\Dbal;


use Tester\Assert;
use function array_map;


require_once __DIR__ . '/../../bootstrap.php';


class PlatformSqliteTest extends IntegrationTestCase
{
	public function testTables()
	{
		$tables = $this->connection->getPlatform()->getTables();

		Assert::true(isset($tables["books"]));
		Assert::same('books', $tables["books"]->name);
		Assert::same(false, $tables["books"]->isView);
	}


	public function testColumns()
	{
		$columns = $this->connection->getPlatform()->getColumns('books');
		$columns = array_map(function ($column) {
			return (array) $column;
		}, $columns);

		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INTEGER',
				'size' => 0,
				'default' => null,
				'isPrimary' => true,
				'isAutoincrement' => true,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'author_id' => [
				'name' => 'author_id',
				'type' => 'int',
				'size' => 0,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'translator_id' => [
				'name' => 'translator_id',
				'type' => 'int',
				'size' => 0,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => true,
				'meta' => [],
			],
			'title' => [
				'name' => 'title',
				'type' => 'varchar',
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
				'type' => 'int',
				'size' => 0,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'ean_id' => [
				'name' => 'ean_id',
				'type' => 'int',
				'size' => 0,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => true,
				'meta' => [],
			],
		], $columns);

		$schemaColumns = $this->connection->getPlatform()->getColumns('authors');
		$schemaColumns = array_map(function ($column) {
			return (array) $column;
		}, $schemaColumns);

		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INTEGER',
				'size' => 0,
				'default' => null,
				'isPrimary' => true,
				'isAutoincrement' => true,
				'isUnsigned' => false,
				'isNullable' => false,
				'meta' => [],
			],
			'name' => [
				'name' => 'name',
				'type' => 'varchar',
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
				'type' => 'varchar',
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
				'type' => 'date',
				'size' => 0,
				'default' => 'NULL',
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
		$keys = array_map(function ($key) {
			return (array) $key;
		}, $keys);

		Assert::same([
			[
				'name' => '0',
				'schema' => '',
				'column' => 'ean_id',
				'refTable' => 'eans',
				'refTableSchema' => '',
				'refColumn' => 'id',
			],
			[
				'name' => '1',
				'schema' => '',
				'column' => 'publisher_id',
				'refTable' => 'publishers',
				'refTableSchema' => '',
				'refColumn' => 'id',
			],
			[
				'name' => '2',
				'schema' => '',
				'column' => 'translator_id',
				'refTable' => 'authors',
				'refTableSchema' => '',
				'refColumn' => 'id',
			],
			[
				'name' => '3',
				'schema' => '',
				'column' => 'author_id',
				'refTable' => 'authors',
				'refTableSchema' => '',
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
		Assert::same('sqlite', $this->connection->getPlatform()->getName());
	}
}


$test = new PlatformSqliteTest();
$test->run();
