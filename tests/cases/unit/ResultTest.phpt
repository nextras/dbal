<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;


use Mockery;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Result\IResultAdapter;
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
		$adapter->shouldReceive('getNormalizers')->once()->andReturn([]);
		$adapter->shouldReceive('seek')->once()->with(0);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First']);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);
		$adapter->shouldReceive('seek')->once()->with(0);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First']);

		$names = [];
		$result = new Result($adapter);
		$result->setValueNormalization(false);
		foreach ($result as $row) {
			$names[] = $row->name;
		}

		Assert::same(['First'], $names);

		Assert::false($result->valid());
		Assert::same(1, $result->key());

		$result->rewind();
		Assert::truthy($result->current());
		Assert::same('First', $result->current()->name);
		Assert::true($result->valid());
		Assert::same(0, $result->key());
	}


	public function testFetchField()
	{
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getNormalizers')->once()->andReturn([]);
		$adapter->shouldReceive('fetch')->times(3)->andReturn(['name' => 'First', 'surname' => 'Two']);

		$result = new Result($adapter);
		$result->setValueNormalization(false);
		Assert::same('First', $result->fetchField());
		Assert::same('Two', $result->fetchField(1));
		Assert::throws(function () use ($result) {
			$result->fetchField(2);
		}, InvalidArgumentException::class);

		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getNormalizers')->once()->andReturn([]);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);

		$result = new Result($adapter);
		$result->setValueNormalization(false);
		Assert::null($result->fetchField());
	}


	public function testFetchAll()
	{
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getNormalizers')->once()->andReturn([]);
		$adapter->shouldReceive('seek')->once();
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'Third', 'surname' => 'Four']);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);
		$adapter->shouldReceive('seek')->once();
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'First', 'surname' => 'Two']);
		$adapter->shouldReceive('fetch')->once()->andReturn(['name' => 'Third', 'surname' => 'Four']);
		$adapter->shouldReceive('fetch')->once()->andReturn(null);

		$result = new Result($adapter);
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
			$adapter->shouldReceive('getNormalizers')->once()->andReturn([]);
			$adapter->shouldReceive('seek')->once();
			$adapter->shouldReceive('fetch')->once()->andReturn($one);
			$adapter->shouldReceive('fetch')->once()->andReturn($two);
			$adapter->shouldReceive('fetch')->once()->andReturn(null);

			$result = new Result($adapter);
			$result->setValueNormalization(false);
			return $result;
		};

		Assert::same([
			10,
			12,
		], $createResult()->fetchPairs(null, 'n'));

		Assert::equal([
			10 => new Row($one),
			12 => new Row($two),
		], $createResult()->fetchPairs('n', null));

		Assert::same([
			10 => 'jon snow',
			12 => 'oberyn martell',
		], $createResult()->fetchPairs('n', 'name'));

		Assert::equal([
			'2014-01-01T00:00:00+01:00' => new Row($one),
			'2014-01-03T00:00:00+01:00' => new Row($two),
		], $createResult()->fetchPairs('born', null));

		Assert::same([
			'2014-01-01T00:00:00+01:00' => 'jon snow',
			'2014-01-03T00:00:00+01:00' => 'oberyn martell',
		], $createResult()->fetchPairs('born', 'name'));

		Assert::exception(function () {
			$adapter = Mockery::mock(IResultAdapter::class);
			$adapter->shouldReceive('getNormalizers')->once()->andReturn([]);
			$result = new Result($adapter);
			$result->setValueNormalization(false);
			$result->fetchPairs();
		}, InvalidArgumentException::class, 'Result::fetchPairs() requires defined key or value.');
	}


	public function testColumns()
	{
		$adapter = Mockery::mock(IResultAdapter::class);
		$adapter->shouldReceive('getNormalizers')->once()->andReturn([]);
		$adapter->shouldReceive('getTypes')->once()->andReturn([
			'age' => 'varchar',
			'123' => 'int',
		]);
		$result = new Result($adapter);

		Assert::same(['age', '123'], $result->getColumns());
	}
}


$test = new ResultTest();
$test->run();
