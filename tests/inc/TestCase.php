<?php declare(strict_types = 1);

namespace NextrasTests\Dbal;


use Mockery;


class TestCase extends \Tester\TestCase
{
	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}
}
