<?php

/**
 * @testCase
 */

namespace NextrasTests\Dbal\Utils;

use Nextras\Dbal\Utils\PostgreArrayConverter;
use NextrasTests\Dbal\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../bootstrap.php';


class PostgreArrayConverterTest extends TestCase
{

	public function testToPhp()
	{
		$result = PostgreArrayConverter::toPhp('{blah,blah blah,123,,"blah \\"\\ ,{\tdaőő",NULL}');
		Assert::same([
			'blah',
			'blah blah',
			123,
			'',
			'blah "\ ,{\tdaőő',
			NULL,
		], $result);

		$result = PostgreArrayConverter::toPhp('{1,3,4,5,7}');
		Assert::same([1, 3, 4, 5, 7], $result);

		$result = PostgreArrayConverter::toPhp('{,}');
		Assert::same(['', ''], $result);

		$result = PostgreArrayConverter::toPhp('{}');
		Assert::same([], $result);

		$result = PostgreArrayConverter::toPhp('');
		Assert::same(NULL, $result);
	}


	public function testToSql()
	{
		$result = PostgreArrayConverter::toSql([
			'blah',
			'blah blah',
			123,
			'',
			'blah "\ ,{\tdaőő',
			NULL,
		]);
		Assert::same('{"blah","blah blah",123,"","blah \\"\\ ,{\tdaőő",NULL}', $result);

		$result = PostgreArrayConverter::toSql([1, 3, 4, 5, 7]);
		Assert::same('{1,3,4,5,7}', $result);

		$result = PostgreArrayConverter::toSql(['', '']);
		Assert::same('{"",""}', $result);

		$result = PostgreArrayConverter::toSql([]);
		Assert::same('{}', $result);

		$result = PostgreArrayConverter::toSql(NULL);
		Assert::same('', $result);
	}

}

$test = new PostgreArrayConverterTest($dic);
$test->run();
