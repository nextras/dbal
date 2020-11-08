<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\PdoPgsql\PdoPgsqlDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\SqlServerPlatform;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class ResultIntegrationTest extends IntegrationTestCase
{
	public function testEmptyResult()
	{
		$result = $this->connection->query('SELECT * FROM books WHERE 1=2');
		Assert::equal([], iterator_to_array($result));
	}


	public function testSetupNormalization()
	{
		$this->initData($this->connection);

		$result = $this->connection->query('SELECT * FROM tag_followers ORDER BY tag_id, author_id');

		$result->setValueNormalization(false); // test reenabling
		$result->setValueNormalization(true);

		$follower = $result->fetch();

		Assert::same(1, $follower->tag_id);
		Assert::same(1, $follower->author_id);
		Assert::type(DateTimeImmutable::class, $follower->created_at);
		Assert::same('2014-01-01 00:10:00', $follower->created_at->format('Y-m-d H:i:s'));

		$result->setValueNormalization(false);
		$follower = $result->fetch();

		if (
			$this->connection->getPlatform() instanceof SqlServerPlatform
			|| $this->connection->getDriver() instanceof PdoPgsqlDriver
		) {
			Assert::same(2, $follower->tag_id);
			Assert::same(2, $follower->author_id);
		} else {
			Assert::same('2', $follower->tag_id);
			Assert::same('2', $follower->author_id);
		}
		Assert::type('string', $follower->created_at);
	}


	public function testSeek()
	{
		$this->initData($this->connection);
		$result = $this->connection->query('SELECT * FROM books ORDER BY id');

		$books = $result->fetchPairs(null, 'id');
		Assert::same([1, 2, 3, 4], $books);

		$books = $result->fetchPairs(null, 'id');
		Assert::same([1, 2, 3, 4], $books);

		$result->seek(1);
		$fetched = $result->fetch();
		Assert::notNull($fetched);
		Assert::same(2, $fetched->id);

		Assert::exception(function () use ($result) {
			$result->seek(10);
		}, InvalidArgumentException::class);
	}


	public function testResultType()
	{
		$this->lockConnection($this->connection);
		Assert::null($this->connection->query('INSERT INTO tags %values', ['name' => "Test"])->fetch());
	}


	public function testCount()
	{
		$this->initData($this->connection);
		$result = $this->connection->query('SELECT * FROM books');
		Assert::same(4, $result->count());
	}
}


$test = new ResultIntegrationTest();
$test->run();
