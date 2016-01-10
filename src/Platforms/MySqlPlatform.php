<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Platforms;

use Nextras\Dbal\Connection;


class MySqlPlatform implements IPlatform
{
	/** @var Connection */
	private $connection;


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}


	public function getName()
	{
		return 'mysql';
	}


	public function getTables()
	{
		$tables = [];
		foreach ($this->connection->query('SHOW FULL TABLES') as $row) {
			$row = array_values($row->toArray());
			$tables[$row[0]] = [
				'name' => $row[0],
				'is_view' => isset($row[1]) && $row[1] === 'VIEW',
			];
		}
		return $tables;
	}


	public function getColumns($table)
	{
		$columns = [];
		foreach ($this->connection->query('SHOW FULL COLUMNS FROM %table', $table) as $row) {
			$type = explode('(', $row->Type);
			$columns[$row->Field] = [
				'name' => $row->Field,
				'type' => strtoupper($type[0]),
				'size' => isset($type[1]) ? (int) $type[1] : NULL,
				'default' => $row->Default,
				'is_primary' => $row->Key === 'PRI',
				'is_autoincrement' => $row->Extra === 'auto_increment',
				'is_unsigned' => (bool) strstr($row->Type, 'unsigned'),
				'is_nullable' => $row->Null === 'YES',
			];
		}
		return $columns;
	}


	public function getForeignKeys($table)
	{
		$result = $this->connection->query('
			SELECT
				CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
			FROM
				information_schema.KEY_COLUMN_USAGE
			WHERE
				TABLE_SCHEMA = DATABASE()
				AND REFERENCED_TABLE_NAME IS NOT NULL
				AND TABLE_NAME = %s
		', $table);

		$keys = [];
		foreach ($result as $row) {
			$keys[$row->COLUMN_NAME] = [
				'name' => $row->CONSTRAINT_NAME,
				'column' => $row->COLUMN_NAME,
				'ref_table' => $row->REFERENCED_TABLE_NAME,
				'ref_column' => $row->REFERENCED_COLUMN_NAME,
			];
		}
		return $keys;
	}


	public function getPrimarySequenceName($table)
	{
		return null;
	}
}
