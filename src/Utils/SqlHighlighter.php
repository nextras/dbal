<?php declare(strict_types = 1);

namespace Nextras\Dbal\Utils;


use function assert;
use function htmlspecialchars;
use function preg_replace_callback;
use function trim;


/**
 * @internal
 */
class SqlHighlighter
{
	use StrictObjectTrait;


	public static function highlight(string $sql): string
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|SHOW|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE|START\s+TRANSACTION|COMMIT|ROLLBACK|(?:RELEASE\s+|ROLLBACK\s+TO\s+)?SAVEPOINT';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|REGEXP|TRUE|FALSE';

		$sql = " $sql ";
		$sql = htmlspecialchars($sql, ENT_IGNORE, 'UTF-8');
		$sql = preg_replace_callback("#(/\\*.+?\\*/)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is", function (
			$matches
		) {
			if (isset($matches[1])) { // comment
				return '<em style="color:gray">' . $matches[1] . '</em>';
			} elseif (isset($matches[2])) { // most important keywords
				return '<strong style="color:#2D44AD">' . $matches[2] . '</strong>';
			} elseif (isset($matches[3])) { // other keywords
				return '<strong>' . $matches[3] . '</strong>';
			} else {
				return $matches[0];
			}
		}, $sql);
		assert($sql !== null);
		return trim($sql);
	}
}
