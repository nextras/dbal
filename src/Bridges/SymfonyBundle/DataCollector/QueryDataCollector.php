<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\SymfonyBundle\DataCollector;

use Nextras\Dbal\Bridges\NetteTracy\ConnectionPanel;
use Nextras\Dbal\DriverException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\SqlHighlighter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use function strlen;
use function substr;
use function ucfirst;
use function uniqid;


class QueryDataCollector extends DataCollector implements ILogger
{
	/** @var IConnection */
	private $connection;

	/** @var bool */
	private $explain;

	/**
	 * @var array
	 * @phpstan-var array<array{string, float, ?int}>
	 */
	private $queries = [];


	public function __construct(IConnection $connection, bool $explain, string $name)
	{
		$this->connection = $connection;
		$this->explain = $explain && $connection->getPlatform()->isSupported(IPlatform::SUPPORT_QUERY_EXPLAIN);
		$this->data['name'] = $name;
		$this->reset();
	}


	public function getName()
	{
		return $this->data['name'];
	}


	public function collect(Request $request, Response $response, \Throwable $exception = null): void
	{
		foreach ($this->queries as [$sqlQuery, $timeTaken, $rowsCount]) {
			$row = [
				'uniqId' => uniqid('nextras-dbal-sql-'),
				'sql' => SqlHighlighter::highlight($sqlQuery),
				'rowsCount' => $rowsCount,
				'timeTaken' => $timeTaken,
				'explain' => null,
			];

			try {
				if ($this->explain) {
					$row['explain'] = $this->connection->getDriver()->query('EXPLAIN ' . $sqlQuery)->fetchAll();
				}
			} catch (\Throwable $e) {
				$row['explain'] = null;
				$row['rowsCount'] = null; // rows count is also irrelevant
			}

			$this->data['queries'][] = $row;
		}
	}


	public function reset(): void
	{
		$this->queries = [];
		$this->data = [
			'name' => $this->data['name'] ?? '',
			'count' => 0,
			'time' => 0.0,
			'queries' => [],
			'whitespaceExplain' => $this->connection->getPlatform()->isSupported(IPlatform::SUPPORT_WHITESPACE_EXPLAIN),
		];
	}


	public function getTitle(): string
	{
		return ucfirst(substr($this->getName(), strlen('nextras_dbal.'), -strlen('.query_data_collector')));
	}


	public function getQueryCount(): int
	{
		return $this->data['count'];
	}


	public function getTotalTime(): float
	{
		return $this->data['time'];
	}


	/**
	 * @return array<mixed>
	 */
	public function getQueries(): array
	{
		return $this->data['queries'];
	}


	public function getWhitespaceExplain(): bool
	{
		return $this->data['whitespaceExplain'];
	}


	public function onConnect(): void
	{
	}


	public function onDisconnect(): void
	{
	}


	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result, ?DriverException $exception): void
	{
		$this->data['count']++;
		if ($this->data['count'] > 100) {
			return;
		}

		$this->data['time'] += $timeTaken;
		$this->queries[] = [
			$sqlQuery,
			$timeTaken,
			$result ? $result->count() : null,
		];
	}
}