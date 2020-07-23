<?php declare(strict_types = 1);

/** @testCase */

namespace NextrasTests\Dbal;


use Nextras\Dbal\Utils\SqlHighlighter;
use Tester\Assert;
use Tester\TestCase;


require_once __DIR__ . '/../../bootstrap.php';


class SqlHighlighterTest extends TestCase
{
	public function testHighlight()
	{
		Assert::same(
			'<strong style="color:#2D44AD">SELECT</strong> * <strong style="color:#2D44AD">FROM</strong> table',
			SqlHighlighter::highlight('SELECT * FROM table')
		);

		Assert::same(
			'<strong style="color:#2D44AD">SELECT</strong> <strong>NULL</strong>',
			SqlHighlighter::highlight("SELECT NULL")
		);
	}
}


(new SqlHighlighterTest())->run();
