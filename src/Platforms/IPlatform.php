<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Platforms;


interface IPlatform
{
	/**
	 * Returns platform name.
	 * @return string
	 */
	public function getName();


	/**
	 * Returns list of tables names indexed by table name.
	 * @return array
	 */
	public function getTables();


	/**
	 * Returns list of table columns metadata, indexed by column name.
	 * @return array
	 */
	public function getColumns($table);


	/**
	 * Returns list of table foreign keys, indexed by column name.
	 * @return array
	 */
	public function getForeignKeys($table);


	/**
	 * Returns primary sequence name for the table.
	 * If not supported nor present, returns a null.
	 * @param  string $table
	 * @return string|null
	 */
	public function getPrimarySequenceName($table);
}
