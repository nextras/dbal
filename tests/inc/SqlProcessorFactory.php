<?php declare(strict_types = 1);

namespace NextrasTests\Dbal;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\SqlProcessor;


class SqlProcessorFactory implements ISqlProcessorFactory
{
	public function create(IConnection $connection): SqlProcessor
	{
		$sqlProcessor = new SqlProcessor($connection->getPlatform());
		$sqlProcessor->setCustomModifier(
			'%pgArray',
			function(SqlProcessor $sqlProcessor, $value, string $type) {
				if (!is_array($value)) throw new InvalidArgumentException('%pgArray modifier accepts an array only.');
				return 'ARRAY[' .
					implode(', ', array_map(function($subValue) use ($sqlProcessor): string {
						return $sqlProcessor->processModifier('any', $subValue);
					}, $value)) .
					']';
			},
		);
		return $sqlProcessor;
	}
}
