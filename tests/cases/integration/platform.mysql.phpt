<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini mysql
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\Mysqli\MysqliDriver;
use Nextras\Dbal\Drivers\PdoMysql\PdoMysqlDriver;
use Nextras\Dbal\Platforms\Data\Fqn;
use Tester\Assert;
use function array_map;
use function version_compare;


require_once __DIR__ . '/../../bootstrap.php';


class PlatformMysqlTest extends IntegrationTestCase
{
	public function testTables()
	{
		$dbName = $this->connection->getConfig()['database'];
		$tables = $this->connection->getPlatform()->getTables();

		Assert::true(isset($tables["$dbName.books"]));
		Assert::same('books', $tables["$dbName.books"]->fqnName->name);
		Assert::same(false, $tables["$dbName.books"]->isView);

		Assert::true(isset($tables["$dbName.my_books"]));
		Assert::same('my_books', $tables["$dbName.my_books"]->fqnName->name);
		Assert::same(true, $tables["$dbName.my_books"]->isView);

		$dbName = $dbName . '2';
		$tables = $this->connection->getPlatform()->getTables($dbName);
		Assert::true(isset($tables["$dbName.authors"]));
		Assert::same('authors', $tables["$dbName.authors"]->fqnName->name);
		Assert::same(false, $tables["$dbName.authors"]->isView);
	}


	public function testColumns()
	{
		$columns = $this->connection->getPlatform()->getColumns('books');
		$columns = array_map(function($table) {
			return (array) $table;
		}, $columns);

		$driver = $this->connection->getDriver();
		if ($driver instanceof MysqliDriver) {
			$isMariaDb = stripos($driver->getResourceHandle()->server_info, 'MariaDB') !== false;
		} elseif ($driver instanceof PdoMysqlDriver) {
			$isMariaDb =
				stripos($driver->getResourceHandle()->getAttribute(\PDO::ATTR_SERVER_VERSION), 'MariaDB') !== false;
		} else {
			$isMariaDb = false;
		}

		$isMySQL8 = version_compare($this->connection->getDriver()->getServerVersion(), '8.0.19') >= 0
			&& !$isMariaDb;

		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INT',
				'size' => $isMySQL8 ? null : 11,
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
				'size' => $isMySQL8 ? null : 11,
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
				'size' => $isMySQL8 ? null : 11,
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
				'size' => $isMySQL8 ? null : 11,
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
				'size' => $isMySQL8 ? null : 11,
				'default' => null,
				'isPrimary' => false,
				'isAutoincrement' => false,
				'isUnsigned' => false,
				'isNullable' => true,
				'meta' => [],
			],
		], $columns);

		$dbName2 = $this->connection->getConfig()['database'] . '2';

		$schemaColumns = $this->connection->getPlatform()->getColumns('authors', $dbName2);
		$schemaColumns = array_map(function($table) {
			return (array) $table;
		}, $schemaColumns);

		Assert::same([
			'id' => [
				'name' => 'id',
				'type' => 'INT',
				'size' => $isMySQL8 ? null : 11,
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
		$this->lockConnection($this->connection);

		$dbName = $this->connection->getConfig()['database'];
		$keys = $this->connection->getPlatform()->getForeignKeys('books');
		$keys = array_map(function($key) {
			return (array) $key;
		}, $keys);

		Assert::equal([
			'author_id' => [
				'fqnName' => new Fqn('books_authors', $dbName),
				'column' => 'author_id',
				'refTable' => new Fqn('authors', $dbName . '2'),
				'refColumn' => 'id',
			],
			'ean_id' => [
				'fqnName' => new Fqn('books_ean', $dbName),
				'column' => 'ean_id',
				'refTable' => new Fqn('eans', $dbName),
				'refColumn' => 'id',
			],
			'publisher_id' => [
				'fqnName' => new Fqn('books_publisher', $dbName),
				'column' => 'publisher_id',
				'refTable' => new Fqn('publishers', $dbName),
				'refColumn' => 'id',
			],
			'translator_id' => [
				'fqnName' => new Fqn('books_translator', $dbName),
				'column' => 'translator_id',
				'refTable' => new Fqn('authors', $dbName . '2'),
				'refColumn' => 'id',
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

		$schemaKeys = $this->connection->getPlatform()->getForeignKeys('book_fk', $dbName2);
		$schemaKeys = array_map(function($key) {
			return (array) $key;
		}, $schemaKeys);
		Assert::equal([
			'book_id' => [
				'fqnName' => new Fqn('book_id', $dbName2),
				'column' => 'book_id',
				'refTable' => new Fqn('books', $dbName),
				'refColumn' => 'id',
			],
		], $schemaKeys);
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
