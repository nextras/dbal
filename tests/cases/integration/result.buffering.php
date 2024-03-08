<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Drivers\Pdo\PdoDriver;
use Nextras\Dbal\Exception\NotSupportedException;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../bootstrap.php';


class ResultBufferingIntegrationTest extends IntegrationTestCase
{
	public function testBuffering()
	{
		if (!$this->connection->getDriver() instanceof PdoDriver) {
			Environment::skip('Explicit buffering is needed only in PDO drivers.');
		}

		$this->initData($this->connection);
		$this->lockConnection($this->connection);

		$buffered = $this->connection->query('SELECT * FROM books ORDER BY id')->buffered();
		Assert::same([1, 2, 3, 4], $buffered->fetchPairs(null, 'id'));
		Assert::same([1, 2, 3, 4], $buffered->fetchPairs(null, 'id')); // repeated
		Assert::same(4, $buffered->count());

		$unbuffered = $this->connection->query('SELECT * FROM books ORDER BY id')->buffered()->unbuffered();
		Assert::same([1, 2, 3, 4], $unbuffered->fetchPairs(null, 'id'));
		Assert::throws(function () use ($unbuffered): void {
			$unbuffered->fetchPairs(null, 'id');
		}, NotSupportedException::class);

		$lateChanged = $this->connection->query('SELECT * FROM books ORDER BY id')->buffered();
		Assert::same([1, 2, 3, 4], $buffered->fetchPairs(null, 'id'));
		$lateChanged->unbuffered();
		Assert::same([1, 2, 3, 4], $buffered->fetchPairs(null, 'id'));
	}
}


$test = new ResultBufferingIntegrationTest();
$test->run();
