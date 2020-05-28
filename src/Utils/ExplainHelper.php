<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


/**
 * @internal
 */
class ExplainHelper
{
	public static function getExplainQuery(string $sql): ?string
	{
		$doExplain = preg_match('#^\s*+\(?\s*+(?:SELECT|INSERT|UPDATE|DELETE|WITH)\s#i', $sql) === 1;
		if (!$doExplain) return null;
		return "EXPLAIN $sql";
	}
}
