<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\Result\Row;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class RowTest extends TestCase
{

	public function testIterator()
	{
		$row = new Row([]);
		Assert::same([], iterator_to_array($row));

		$row = new Row(['test' => 'One', NULL => 'two']);
		Assert::same(['test' => 'One', NULL => 'two'], iterator_to_array($row));
	}


	public function testPropertyAccess()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);

		Assert::same('Jon', $row->name);
		Assert::same('Snow', $row->surname);

		Assert::true(isset($row->name));
		Assert::false(isset($row->NAME));

		Assert::throws(function() use ($row) {
			$row->NAME;
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', "Undefined property 'NAME'.");

		Assert::throws(function() use ($row) {
			$row->NAME = 'Peter';
		}, 'Nextras\Dbal\Exceptions\NotSupportedException', 'Row is read-only.');

		Assert::throws(function() use ($row) {
			unset($row->name);
		}, 'Nextras\Dbal\Exceptions\NotSupportedException', 'Row is read-only.');
	}


	public function testArrayAccess()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);

		Assert::same('Jon', $row[0]);
		Assert::same('Snow', $row[1]);

		Assert::true(isset($row[0]));
		Assert::false(isset($row[2]));

		Assert::throws(function() use ($row) {
			$row[3];
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', "Undefined offset '3'.");

		Assert::throws(function() use ($row) {
			$row[2] = 'Peter';
		}, 'Nextras\Dbal\Exceptions\NotSupportedException', 'Row is read-only.');

		Assert::throws(function() use ($row) {
			unset($row[0]);
		}, 'Nextras\Dbal\Exceptions\NotSupportedException', 'Row is read-only.');
	}


	public function testReadonlyArrayArg()
	{
		$row = new Row(['names' => ['One', 'Two']]);

		if (defined('HHVM_VERSION')) {
			$row->names[] = 'Three';
		} else {
			Assert::error(function () use ($row) {
				$row->names[] = 'Three';
			}, E_NOTICE);
		}

		Assert::same(['One', 'Two'], $row->names);
	}

}


$test = new RowTest();
$test->run();
