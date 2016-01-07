<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteTracy;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Result\Result;
use Tracy\Debugger;
use Tracy\IBarPanel;


class ConnectionPanel implements IBarPanel
{
	/** @var int */
	private $maxQueries = 100;

	/** @var int */
	private $count = 0;

	/** @var float */
	private $totalTime;

	/** @var array */
	private $queries = [];


	public static function install(Connection $connection)
	{
		Debugger::getBar()->addPanel(new ConnectionPanel($connection));
	}


	public function __construct(Connection $connection)
	{
		$connection->onQuery[] = [$this, 'logQuery'];
	}


	public function logQuery(Connection $connection, $sql, Result $result = NULL)
	{
		$this->count++;
		if ($this->count > $this->maxQueries) {
			return;
		}

		$this->queries[] = [
			$connection,
			$sql,
			$result ? $result->getElapsedTime() : NULL,
		];

		if ($result) {
			$this->totalTime += $result->getElapsedTime();
		}
	}


	public function getTab()
	{
		$count = $this->count;
		$totalTime = $this->totalTime;

		ob_start();
		require __DIR__ . '/ConnectionPanel.tab.phtml';
		return ob_get_clean();
	}


	public function getPanel()
	{
		$count = $this->count;
		$queries = $this->queries;
		$queries = array_map(function($row) {
			$row[1] = self::highlight($row[1]);
			return $row;
		}, $queries);

		ob_start();
		require __DIR__ . '/ConnectionPanel.panel.phtml';
		return ob_get_clean();
	}


	public static function highlight($sql)
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|SHOW|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|REGEXP|TRUE|FALSE';

		$sql = " $sql ";
		$sql = htmlSpecialChars($sql, ENT_IGNORE, 'UTF-8');
		$sql = preg_replace_callback("#(/\\*.+?\\*/)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is", function($matches) {
			if (!empty($matches[1])) { // comment
				return '<em style="color:gray">' . $matches[1] . '</em>';
			} elseif (!empty($matches[2])) { // most important keywords
				return '<strong style="color:#2D44AD">' . $matches[2] . '</strong>';
			} elseif (!empty($matches[3])) { // other keywords
				return '<strong>' . $matches[3] . '</strong>';
			}
		}, $sql);

		return trim($sql);
	}
}
