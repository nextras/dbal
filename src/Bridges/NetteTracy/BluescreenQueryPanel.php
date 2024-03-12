<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\NetteTracy;


use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Utils\SqlHighlighter;


class BluescreenQueryPanel
{
	/**
	 * @return array{tab: string, panel: string}|null
	 */
	public static function renderBluescreenPanel(?\Throwable $exception): ?array
	{
		if (!$exception instanceof QueryException || ($query = $exception->getSqlQuery()) === null) {
			return null;
		}

		return [
			'tab' => 'SQL',
			'panel' => '<pre class="sql">' . SqlHighlighter::highlight($query) . '</pre>' .
				"<p>Error code: {$exception->getErrorCode()}<br>SQL STATE: {$exception->getErrorSqlState()}</p>",
		];
	}
}
