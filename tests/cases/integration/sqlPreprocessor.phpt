<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\Result\Row;
use Nextras\Dbal\SqlProcessor;
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
		$sqlProcessorFactory = new class implements ISqlProcessorFactory {
			public function create(IConnection $connection): SqlProcessor
			{
				$sqlProcessor = new SqlProcessor($connection->getPlatform());
				$sqlProcessor->setCustomModifier(
					'%test',
					function (SqlProcessor $sqlProcessor, $value, string $type) {
						if (!is_array($value)) throw new InvalidArgumentException('%test modifer accepts only array.');
						return 'ARRAY[' .
							implode(', ', array_map(function ($subValue) use ($sqlProcessor): string {
								return $sqlProcessor->processModifier('any', $subValue);
							}, $value)) .
							']';
					}
				);
				return $sqlProcessor;
			}
		};

		$this->connection->connect();
		$sqlProcessor = $sqlProcessorFactory->create($this->connection);
		$result = $sqlProcessor->processModifier('%test', [1, '2', false, null]);
		Assert::same($result, "ARRAY[1, '2', 0, NULL]");
	}
}


$test = new SqlPreprocessorIntegrationTest();
$test->run();
