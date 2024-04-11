<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider? ../../databases.ini pgsql
 */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\Data\Fqn;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class ConnectionPostgresTest extends IntegrationTestCase
{
	public function testReconnect()
	{
		$processId = $this->connection->query('SELECT pg_backend_pid()')->fetchField();
		Assert::same($processId, $this->connection->query('SELECT pg_backend_pid()')->fetchField());
		$this->connection->reconnect();
		Assert::notSame($processId, $this->connection->query('SELECT pg_backend_pid()')->fetchField());
	}


	public function testLastInsertId()
	{
		$this->initData($this->connection);

		$this->connection->query('INSERT INTO publishers %values', ['name' => 'FOO']);
		Assert::same(2, $this->connection->getLastInsertedId('publishers_id_seq'));
		Assert::same(2, $this->connection->getLastInsertedId('public.publishers_id_seq'));
		Assert::same(2, $this->connection->getLastInsertedId('"public"."publishers_id_seq"'));
		Assert::same(2, $this->connection->getLastInsertedId(new Fqn(schema: 'public', name: 'publishers_id_seq')));

		Assert::exception(function() {
			$this->connection->getLastInsertedId();
		}, InvalidArgumentException::class, 'PgsqlDriver requires passing a sequence name for getLastInsertedId() method.');
	}


	public function testSequenceCasing()
	{
		$this->lockConnection($this->connection);
		$this->connection->query('DROP SEQUENCE IF EXISTS %column', "MySequence");
		$this->connection->query('CREATE SEQUENCE %column INCREMENT 5 START 10;', "MySequence");
		$this->connection->query('SELECT NEXTVAL(\'%column\')', "MySequence");
		Assert::same(10, $this->connection->getLastInsertedId('"MySequence"'));
	}
}


$test = new ConnectionPostgresTest();
$test->run();
