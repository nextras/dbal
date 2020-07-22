<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\NetteTracy;


use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\ExplainHelper;
use Nextras\Dbal\Utils\SqlHighlighter;
use Tracy\Debugger;
use Tracy\IBarPanel;


class ConnectionPanel implements IBarPanel, ILogger
{
	/** @var int */
	private $maxQueries = 100;

	/** @var int */
	private $count = 0;

	/** @var float */
	private $totalTime; // @phpstan-ignore-line

	/**
	 * @var array
	 * @phpstan-var array<array{IConnection, string, float, ?int}>
	 */
	private $queries = [];

	/** @var IConnection */
	private $connection;

	/** @var bool */
	private $doExplain;


	public static function install(IConnection $connection, bool $doExplain = true): void
	{
		$doExplain = $doExplain && $connection->getPlatform()->isSupported(IPlatform::SUPPORT_QUERY_EXPLAIN);
		Debugger::getBar()->addPanel(new ConnectionPanel($connection, $doExplain));
	}


	public function __construct(IConnection $connection, bool $doExplain)
	{
		$connection->addLogger($this);
		$this->connection = $connection;
		$this->doExplain = $doExplain;
	}


	public function onConnect(): void
	{
	}


	public function onDisconnect(): void
	{
	}


	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result): void
	{
		$this->count++;
		if ($this->count > $this->maxQueries) {
			return;
		}

		$this->totalTime += $timeTaken;
		$this->queries[] = [
			$this->connection,
			$sqlQuery,
			$timeTaken,
			$result !== null ? $result->count() : null,
		];
	}


	public function onQueryException(string $sqlQuery, float $timeTaken, ?DriverException $exception): void
	{
	}


	public function getTab(): string
	{
		$count = $this->count;
		$totalTime = $this->totalTime;

		ob_start();
		require __DIR__ . '/ConnectionPanel.tab.phtml';
		return (string) ob_get_clean();
	}


	public function getPanel(): string
	{
		$count = $this->count;
		$queries = $this->queries;
		$queries = array_map(function ($row): array {
			try {
				$row[4] = null;
				if ($this->doExplain) {
					$explainSql = ExplainHelper::getExplainQuery($row[1]);
					if ($explainSql !== null) {
						$row[4] = $this->connection->getDriver()->query($explainSql)->fetchAll();
					}
				}
			} catch (\Throwable $e) {
				$row[4] = null;
				$row[3] = null; // rows count is also irrelevant
			}

			$row[1] = SqlHighlighter::highlight($row[1]);
			return $row;
		}, $queries);
		$whitespaceExplain = $this->connection->getPlatform()->isSupported(IPlatform::SUPPORT_WHITESPACE_EXPLAIN);

		ob_start();
		require __DIR__ . '/ConnectionPanel.panel.phtml';
		return (string) ob_get_clean();
	}
}
