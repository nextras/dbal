<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

use Mockery;
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Drivers\IResultAdapter;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Result\Row;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Tester\Assert;


require_once __DIR__ . '/../../bootstrap.php';


class ResultTest extends TestCase
{
	public function testIterator()
	{
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('seek')->once()->with(0);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First']);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);
		$adapter->shouldReceive('seek')->once()->with(0);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First']);

		$driver = Mockery::mock(IDriver::class);

		$names = [];
		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(false);
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
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('seek')->once();
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'Third', 'surname' => 'Four']);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);

		$driver = Mockery::mock(IDriver::class);

		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(false);

		$names = [];
		$result->seek(0);
		while ($row = $result->fetch()) {
			$names[] = $row->name;
		}

		Assert::same(['First', 'Third'], $names);
	}


	public function testFetchField()
	{
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('fetch')->times(3)->andReturn(['name' => 'First', 'surname' => 'Two']);

		$driver = Mockery::mock(IDriver::class);

		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(false);
		Assert::same('First', $result->fetchField());
		Assert::same('Two', $result->fetchField(1));
		Assert::throws(function () use ($result) {
			$result->fetchField(2);
		}, InvalidArgumentException::class);


		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);

		$driver = Mockery::mock(IDriver::class);

		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(false);
		Assert::null($result->fetchField());
	}


	public function testFetchAll()
	{
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getTypes')->once()->andReturn([]);
		$adapter->shouldReceive('seek')->once();
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'Third', 'surname' => 'Four']);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);
		$adapter->shouldReceive('seek')->once();
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'Third', 'surname' => 'Four']);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);

		$driver = Mockery::mock(IDriver::class);

		$result = new Result($adapter, $driver, 0);
		$result->setValueNormalization(false);

		Assert::equal([
			new Row(['name' => 'First', 'surname' => 'Two']),
			new Row(['name' => 'Third', 'surname' => 'Four']),
		], $result->fetchAll());

		Assert::equal([
			new Row(['name' => 'First', 'surname' => 'Two']),
			new Row(['name' => 'Third', 'surname' => 'Four']),
		], $result->fetchAll());
	}


	public function testFetchPairs()
	{
		$one = [
			'name' => 'jon snow',
			'born' => new DateTimeImmutable('2014-01-01'),
			'n' => 10,
		];
		$two = [
			'name' => 'oberyn martell',
			'born' => new DateTimeImmutable('2014-01-03'),
			'n' => 12,
		];
		$createResult = function () use ($one, $two) {
			$adapter = Mockery::mock(IResultAdapter::class);
			$adapter->shouldReceive('getTypes')->once()->andReturn([]);
			$adapter->shouldReceive('seek')->once();
			$adapter->shouldReceive('fetch')->once()->andReturn($one);
			$adapter->shouldReceive('fetch')->once()->andReturn($two);
			$adapter->shouldReceive('fetch')->once()->andReturn(null);

			$driver = Mockery::mock(IDriver::class);
			$result = new Result($adapter, $driver, 0);
			$result->setValueNormalization(false);
			return $result;
		};

		Assert::same([
			10,
			12,
		], $createResult()->fetchPairs(NULL, 'n'));

		Assert::equal([
			10 => new Row($one),
			12 => new Row($two),
		], $createResult()->fetchPairs('n', NULL));

		Assert::same([
			10 => 'jon snow',
			12 => 'oberyn martell',
		], $createResult()->fetchPairs('n', 'name'));

		Assert::equal([
			'2014-01-01T00:00:00+01:00' => new Row($one),
			'2014-01-03T00:00:00+01:00' => new Row($two),
		], $createResult()->fetchPairs('born', NULL));

		Assert::same([
			'2014-01-01T00:00:00+01:00' => 'jon snow',
			'2014-01-03T00:00:00+01:00' => 'oberyn martell',
		], $createResult()->fetchPairs('born', 'name'));

		Assert::exception(function () {
			$adapter = Mockery::mock(IResultAdapter::class);
			$adapter->shouldReceive('getTypes')->once()->andReturn([]);
			$driver = Mockery::mock(IDriver::class);
			$result = new Result($adapter, $driver, 0);
			$result->setValueNormalization(false);
			$result->fetchPairs();
		}, InvalidArgumentException::class, 'Result::fetchPairs() requires defined key or value.');
	}


	public function testNormalization()
	{
		$one = [
			'name' => 'jon snow',
			'age' => '16',
			'weight' => '90.5',
			'is_single' => 'Yes',
			'born' => '2015-01-01 20:00:00',
		];
		$two = [
			'name' => 'oberyn martell',
			'age' => '20',
			'weight' => '60.5',
			'is_single' => '',
			'born' => '2015-02-01 20:00:00',
		];

		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getTypes')->once()->andReturn([
			'name' => [IResultAdapter::TYPE_STRING, null],
			'age' => [IResultAdapter::TYPE_INT, null],
			'weight' => [IResultAdapter::TYPE_FLOAT, null],
			'is_single' => [IResultAdapter::TYPE_BOOL, null],
			'born' => [IResultAdapter::TYPE_DATETIME, null],
		]);
		$adapter->shouldReceive('fetch')->once()->andReturn($one);
		$adapter->shouldReceive('fetch')->once()->andReturn($two);
		$driver = Mockery::mock(IDriver::class);

		$result = new Result($adapter, $driver, 0);
		$row = $result->fetch();
		Assert::same('jon snow', $row->name);
		Assert::same(16, $row->age);
		Assert::same(90.5, $row->weight);
		Assert::same(true, $row->is_single);
		Assert::same('2015-01-01 20:00:00', $row->born->format('Y-m-d H:i:s'));

		$row = $result->fetch();
		Assert::same('oberyn martell', $row->name);
		Assert::same(20, $row->age);
		Assert::same(60.5, $row->weight);
		Assert::same(false, $row->is_single);
		Assert::same('2015-02-01 20:00:00', $row->born->format('Y-m-d H:i:s'));
	}
}


$test = new ResultTest();
$test->run();
