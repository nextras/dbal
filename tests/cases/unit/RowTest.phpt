<?php

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\NotSupportedException;
use Nextras\Dbal\Result\Row;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';


class RowTest extends TestCase
{
	public function testPropertyAccess()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);

		Assert::same('Jon', $row->name);
		Assert::same('Snow', $row->surname);

		Assert::true(isset($row->name));
		Assert::false(isset($row->NAME));

		Assert::throws(function() use ($row) {
			$row->NAME;
		}, InvalidArgumentException::class, "Column 'NAME' does not exist.");

		Assert::throws(function() use ($row) {
			$row->suname;
		}, InvalidArgumentException::class, "Column 'suname' does not exist, did you mean 'surname'?");
	}


	public function testArrayAccess()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);
		Assert::same('Jon', $row[0]);
		Assert::same('Snow', $row[1]);
		Assert::true(isset($row[0]));
		Assert::true(isset($row[1]));

		Assert::false(isset($row[2]));
		Assert::false(isset($row[-1]));

		Assert::throws(function () use ($row) {
			$row[2];
		}, InvalidArgumentException::class);

		Assert::throws(function () use ($row) {
			$row['name'];
		}, NotSupportedException::class);

		Assert::throws(function () use ($row) {
			isset($row['name']);
		}, NotSupportedException::class);

		Assert::throws(function () use ($row) {
			unset($row['name']);
		}, NotSupportedException::class);

		Assert::throws(function () use ($row) {
			$row['name'] = 'bar';
		}, NotSupportedException::class);
	}


	public function testToArray()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);
		Assert::same(['name' => 'Jon', 'surname' => 'Snow'], $row->toArray());
	}
}


$test = new RowTest();
$test->run();
