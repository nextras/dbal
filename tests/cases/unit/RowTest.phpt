<?php

/** @testCase */

namespace NextrasTests\Dbal;

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
		}, 'Nextras\Dbal\InvalidArgumentException', "Column 'NAME' does not exist.");

		Assert::throws(function() use ($row) {
			$row->suname;
		}, 'Nextras\Dbal\InvalidArgumentException', "Column 'suname' does not exist, did you mean 'surname'?");
	}


	public function testToArray()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);
		Assert::same(['name' => 'Jon', 'surname' => 'Snow'], $row->toArray());
	}

}


$test = new RowTest();
$test->run();
