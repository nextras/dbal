<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Result\Row;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ResultTest extends TestCase
{

	public function testIterator()
	{
		$adapter = Mockery::mock('Nextras\Dbal\Drivers\IResultAdapter');
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('seek')->once()->with(0);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First']);
		$adapter->shouldReceive('fetch')->once()->andReturn(NULL);
		$adapter->shouldReceive('seek')->once()->with(0);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First']);

		$driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');

		$names = [];
		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(FALSE);
		foreach ($result as $row) {
			$names[] = $row->name;
		}

		Assert::same(['First'], $names);

		Assert::false($result->valid());
		Assert::null($result->current());
		Assert::same(1, $result->key());

		$result->rewind();
		Assert::truthy($result->current());
		Assert::same('First', $result->current()->name);
		Assert::true($result->valid());
		Assert::same(0, $result->key());
	}


	public function testSeek()
	{
		$adapter = Mockery::mock('Nextras\Dbal\Drivers\IResultAdapter');
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('seek')->once();
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'Third', 'surname' => 'Four']);
		$adapter->shouldReceive('fetch')->once()->andReturn(NULL);

		$driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');

		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(FALSE);

		$names = [];
		$result->seek(0);
		while ($row = $result->fetch()) {
			$names[] = $row->name;
		}

		Assert::same(['First', 'Third'], $names);
	}


	public function testFetchField()
	{
		$adapter = Mockery::mock('Nextras\Dbal\Drivers\IResultAdapter');
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);

		$driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');

		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(FALSE);
		Assert::same('First', $result->fetchField());


		$adapter = Mockery::mock('Nextras\Dbal\Drivers\IResultAdapter');
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('fetch')->once()->andReturn(NULL);

		$driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');

		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(FALSE);
		Assert::null($result->fetchField());
	}


	public function testFetchAll()
	{
		$adapter = Mockery::mock('Nextras\Dbal\Drivers\IResultAdapter');
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('seek')->once();
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'Third', 'surname' => 'Four']);
		$adapter->shouldReceive('fetch')->once()->andReturn(NULL);
		$adapter->shouldReceive('seek')->once();
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'Third', 'surname' => 'Four']);
		$adapter->shouldReceive('fetch')->once()->andReturn(NULL);

		$driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');

		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(FALSE);

		Assert::equal([
			new Row(['name' => 'First', 'surname' => 'Two']),
			new Row(['name' => 'Third', 'surname' => 'Four']),
		], $result->fetchAll());

		Assert::equal([
			new Row(['name' => 'First', 'surname' => 'Two']),
			new Row(['name' => 'Third', 'surname' => 'Four']),
		], $result->fetchAll());
	}

}


$test = new ResultTest();
$test->run();
