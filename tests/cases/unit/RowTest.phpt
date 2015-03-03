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
		}, 'Nextras\Dbal\Exceptions\InvalidArgumentException', "Column 'NAME' does not exist.");
	}


	public function testToArray()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);
		Assert::same(['name' => 'Jon', 'surname' => 'Snow'], $row->toArray());
	}

}


$test = new RowTest();
$test->run();
