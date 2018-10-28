<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;

use Nextras\Dbal\InvalidArgumentException;
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

		Assert::throws(function () use ($row) {
			$row->NAME;
		}, InvalidArgumentException::class, "Column 'NAME' does not exist.");

		Assert::throws(function () use ($row) {
			$row->Name;
		}, InvalidArgumentException::class, "Column 'Name' does not exist, did you mean 'name'?");

		Assert::throws(function () use ($row) {
			$row->suname;
		}, InvalidArgumentException::class, "Column 'suname' does not exist, did you mean 'surname'?");
	}


	public function testGetNthField()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);
		Assert::same('Jon', $row->getNthField(0));
		Assert::same('Snow', $row->getNthField(1));
	}


	public function testToArray()
	{
		$row = new Row(['name' => 'Jon', 'surname' => 'Snow']);
		Assert::same(['name' => 'Jon', 'surname' => 'Snow'], $row->toArray());
	}
}


$test = new RowTest();
$test->run();
