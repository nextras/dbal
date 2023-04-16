<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Dbal\Result\Row;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class SqlPreprocessorIntegrationTest extends IntegrationTestCase
{
	public function testEmptyInsert()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DELETE FROM table_with_defaults');
		$this->connection->query('INSERT INTO table_with_defaults %values', []);
		$this->connection->query('INSERT INTO table_with_defaults %values[]', [[]]);
		$this->connection->query('INSERT INTO table_with_defaults %values[]', [[], []]);
		$count = $this->connection->query('SELECT COUNT(*) FROM table_with_defaults')->fetchField();
		Assert::equal(4, $count);
	}


	public function testMultiOr()
	{
		$this->initData($this->connection);
		$query = [
			['book_id' => 1, 'tag_id' => 1],
			['book_id' => 2, 'tag_id' => 3],
			['book_id' => 3, 'tag_id' => 3],
		];

		$rows = $this->connection->query('
			SELECT * FROM books_x_tags
			WHERE %multiOr
		', $query)->fetchAll();

		Assert::same($query, array_map(function (Row $row) {
			return $row->toArray();
		}, $rows));
	}


	public function testCustomModifier()
	{
		$this->connection->connect();
		/** @var ISqlProcessorFactory $sqlProcessorFactory */
		$sqlProcessorFactory = $this->connection->getConfig()['sqlProcessorFactory'];
		$sqlProcessor = $sqlProcessorFactory->create($this->connection);
		$result = $sqlProcessor->processModifier('%pgArray', [1, '2', false, null]);
		if ($this->connection->getPlatform()->getName() === PostgreSqlPlatform::NAME) {
			Assert::same("ARRAY[1, '2', FALSE, NULL]", $result);
		} else {
			Assert::same("ARRAY[1, '2', 0, NULL]", $result);
		}
	}
}


$test = new SqlPreprocessorIntegrationTest();
$test->run();
