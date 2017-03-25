<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteTracy;

use Nextras\Dbal\QueryException;


class BluescreenQueryPanel
{
	public static function renderBluescreenPanel($exception)
	{
		if (!$exception instanceof QueryException || !($query = $exception->getSqlQuery())) {
			return null;
		}

		return [
			'tab' => 'SQL',
			'panel' => '<pre class="sql">' . ConnectionPanel::highlight($query) . "</pre>",
		];
	}
}
