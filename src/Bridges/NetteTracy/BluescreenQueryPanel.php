<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteTracy;

use Nextras\Dbal\QueryException;
use Nextras\Dbal\Utils\SqlHighlighter;


class BluescreenQueryPanel
{
	/**
	 * @phpstan-return array{tab: string, panel: string}|null
	 */
	public static function renderBluescreenPanel(?\Throwable $exception): ?array
	{
		if (!$exception instanceof QueryException || !($query = $exception->getSqlQuery())) {
			return null;
		}

		return [
			'tab' => 'SQL',
			'panel' => '<pre class="sql">' . SqlHighlighter::highlight($query) . "</pre>",
		];
	}
}
