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
	 * Returns the closest word to the $current word which is not farther than $maxDistance
	 * or NULL if such word does not exist.
	 * @param  string[] $words
	 */
	public static function getClosest(string $current, array $words, int $maxDistance): ?string
	{
		$shortest = $maxDistance + 1;
		$closest = NULL;
		foreach ($words as $word) {
			$distance = levenshtein($current, $word);
			if ($distance < $shortest) {
				$closest = $word;
			}
		}
		return $closest;
	}
}
