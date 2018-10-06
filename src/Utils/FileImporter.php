<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Utils;

use Nextras\Dbal\IConnection;
use Nextras\Dbal\IOException;


class FileImporter
{
	/**
	 * Imports & executes queries from sql file.
	 * Code taken from Adminer (http://www.adminer.org) & modified,
	 * @author Jakub Vrána
	 * @author Jan Tvrdík
	 * @author Michael Moravec
	 * @author Jan Škrášek
	 * @license Apache License
	 *
	 * @return int number of executed queries
	 */
	public static function executeFile(IConnection $connection, string $file): int
	{
		$query = @file_get_contents($file);
		if ($query === false) {
			throw new IOException("Cannot open file '$file'.");
		}

		$delimiter = ';';
		$offset = $queries = 0;
		$space = "(?:\\s|/\\*.*\\*/|(?:#|-- )[^\\n]*\\n|--\\n)";

		$platformName = $connection->getPlatform()->getName();
		if ($platformName === 'mysql') {
			$parse = '[\'"]|/\*|-- |$';
		} elseif ($platformName === 'pgsql') {
			$parse = '[\'"]|/\*|-- |$|\$[^$]*\$';
		} elseif ($platformName === 'mssql') {
			$parse = '[\'"[]|/\*|-- |$';
		} else { // general
			$parse = '[\'"`#]|/\*|-- |$';
		}

		while ($query != '') {
			if (!$offset && preg_match("~^{$space}*DELIMITER\\s+(\\S+)~i", $query, $match)) {
				$delimiter = $match[1];
				$query = substr($query, strlen($match[0]));

			} else {
				preg_match('(' . preg_quote($delimiter) . "\\s*|$parse)", $query, $match, PREG_OFFSET_CAPTURE, $offset); // should always match
				$found = $match[0][0];
				/** @var int $offset */
				$offset = $match[0][1] + strlen($found);

				if (!$found && rtrim($query) === '') {
					break;
				}

				if (!$found || rtrim($found) == $delimiter) { // end of a query
					$q = substr($query, 0, $match[0][1]);

					$queries++;
					$connection->query('%raw', $q);

					$query = substr($query, $offset);
					/** @var int $offset */
					$offset = 0;

				} else { // find matching quote or comment end
					while (preg_match('(' . ($found == '/*' ? '\*/' : ($found == '[' ? ']' : (preg_match('~^-- |^#~', $found) ? "\n" : preg_quote($found) . "|\\\\."))) . '|$)s', $query, $match, PREG_OFFSET_CAPTURE, $offset)) { //! respect sql_mode NO_BACKSLASH_ESCAPES
						$s = $match[0][0];
						/** @var int $offset */
						$offset = $match[0][1] + strlen($s);
						if ($s[0] !== '\\') {
							break;
						}
					}
				}
			}
		}

		return $queries;
	}
}
