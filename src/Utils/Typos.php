<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;


class Typos
{
	/**
	 * Returns the closest word to the $current word or NULL if such word does not exist.
	 * @param  string[] $words
	 */
	public static function getClosest(string $current, array $words): ?string
	{
		$maxDistance = strlen($current) / 4 + 1;
		$closest = null;
		foreach (array_unique($words, SORT_REGULAR) as $word) {
			$distance = levenshtein($current, $word);
			if ($distance > 0 && $distance < $maxDistance) {
				$maxDistance = $distance;
				$closest = $word;
			}
		}
		return $closest;
	}
}
