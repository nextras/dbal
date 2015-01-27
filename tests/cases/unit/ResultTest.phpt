<?php

/** @testcase */

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Result\Result;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class ResultTest extends TestCase
{

	public function testIterator()
	{
		$adapter = Mockery::mock('Nextras\Dbal\Drivers\IResultAdapter');
		$adapter->shouldReceive('seek')->once()->with(0);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First']);
		$adapter->shouldReceive('fetch')->once()->andReturn(NULL);
		$adapter->shouldReceive('seek')->once()->with(0);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First']);

		$driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');

		$names = [];
		$rowset = new Result($adapter, $driver);
		$rowset->setColumnValueNormalization(FALSE);
		foreach ($rowset as $row) {
			$names[] = $row->name;
		}

		Assert::same(['First'], $names);

		Assert::false($rowset->valid());
		Assert::null($rowset->current());
		Assert::same(1, $rowset->key());

		$rowset->rewind();
		Assert::truthy($rowset->current());
		Assert::same('First', $rowset->current()->name);
		Assert::true($rowset->valid());
		Assert::same(0, $rowset->key());
	}


	public function testFetchField()
	{
		$adapter = Mockery::mock('Nextras\Dbal\Drivers\IResultAdapter');
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);

		$driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');

		$rowset = new Result($adapter, $driver);
		$rowset->setColumnValueNormalization(FALSE);
		Assert::same('First', $rowset->fetchField());


		$adapter = Mockery::mock('Nextras\Dbal\Drivers\IResultAdapter');
		$adapter->shouldReceive('fetch')->once()->andReturn(NULL);

		$driver = Mockery::mock('Nextras\Dbal\Drivers\IDriver');

		$rowset = new Result($adapter, $driver);
		$rowset->setColumnValueNormalization(FALSE);
		Assert::null($rowset->fetchField());

	}

}


$test = new ResultTest();
$test->run();
