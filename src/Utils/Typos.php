<?php

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
	 *
	 * @param  string $current
	 * @param  string[] $words
	 * @param  int $maxDistance
	 * @return string|NULL
	 */
	public static function getClosest($current, array $words, $maxDistance)
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
