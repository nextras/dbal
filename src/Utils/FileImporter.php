<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use Nextras\Dbal\Exception\IOException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\MySqlPlatform;
use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Dbal\Platforms\SqlServerPlatform;


class FileImporter
{
	use StrictObjectTrait;


	/**
	 * Imports & executes queries from sql file.
	 * Code taken from Adminer (http://www.adminer.org) & modified,
	 * @return int number of executed queries
	 * @author Jakub Vrána
	 * @author Jan Tvrdík
	 * @author Michael Moravec
	 * @author Jan Škrášek
	 * @license Apache License
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
		if ($platformName === MySqlPlatform::NAME) {
			$parse = '[\'"]|/\*|-- |$';
		} elseif ($platformName === PostgreSqlPlatform::NAME) {
			$parse = '[\'"]|/\*|-- |$|\$[^$]*\$';
		} elseif ($platformName === SqlServerPlatform::NAME) {
			$parse = '[\'"[]|/\*|-- |$';
		} else { // general
			$parse = '[\'"`#]|/\*|-- |$';
		}

		while ($query != '') {
			if ($offset === 0 && preg_match("~^{$space}*DELIMITER\\s+(\\S+)~i", $query, $match) === 1) {
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
					while (preg_match('(' . ($found == '/*' ? '\*/' : ($found == '[' ? ']' : (preg_match('~^-- |^#~', $found) === 1 ? "\n" : preg_quote($found) . "|\\\\."))) . '|$)s', $query, $match, PREG_OFFSET_CAPTURE, $offset) === 1) { //! respect sql_mode NO_BACKSLASH_ESCAPES
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
