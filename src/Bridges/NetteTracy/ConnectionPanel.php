<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteTracy;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Result\Result;
use Tracy\IBarPanel;


class ConnectionPanel implements IBarPanel
{
	/** @var int */
	private $maxQueries = 100;

	/** @var int */
	private $count = 0;

	/** @var array */
	private $queries = [];


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
		];
	}


	public function getTab()
	{
		$count = $this->count;

		ob_start();
		require __DIR__ . '/ConnectionPanel.tab.phtml';
		return ob_get_clean();
	}


	public function getPanel()
	{
		$count = $this->count;
		$queries = $this->queries;

		ob_start();
		require __DIR__ . '/ConnectionPanel.panel.phtml';
		return ob_get_clean();
	}

}
