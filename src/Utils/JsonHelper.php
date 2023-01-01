<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use Nextras\Dbal\Exception\InvalidArgumentException;


/**
 * @internal
 */
class JsonHelper
{
	public static function safeEncode(mixed $value): string
	{
		$encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new InvalidArgumentException('JSON Encode Error: ' . json_last_error_msg());
		}
		assert(is_string($encoded));
		return $encoded;
	}
}
