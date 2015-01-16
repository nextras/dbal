<?php

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
