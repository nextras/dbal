<?php declare(strict_types = 1);

namespace Nextras\Dbal;


interface ISqlProcessorFactory
{
	public function create(Connection $connection): SqlProcessor;
}
