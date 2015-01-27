<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\QueryBuilder;

use Nextras\Dbal\Drivers\IDriver;


class QueryBuilder
{
	/** @const */
	const TYPE_SELECT = 1;
	const TYPE_INSERT = 2;
	const TYPE_UPDATE = 3;
	const TYPE_DELETE = 4;

	/** @var int */
	private $type = self::TYPE_SELECT;

	/** @var array */
	private $args = [
		'select' => NULL,
		'from' => NULL,
		'join' => NULL,
		'where' => NULL,
		'group' => NULL,
		'having' => NULL,
		'order' => NULL,
	];

	/** @var array|NULL */
	private $select = ['*'];

	/** @var array|NULL */
	private $from;

	/** @var array|NULL */
	private $join;

	/** @var array|NULL */
	private $where;

	/** @var array|NULL */
	private $group;

	/** @var array|NULL */
	private $having;

	/** @var array|NULL */
	private $order;

	/** @var array|NULL */
	private $limit;

	/** @var string */
	private $generatedSql;


	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
	}


	public function getQuerySQL()
	{
		if ($this->generatedSql !== NULL) {
			return $this->generatedSql;
		}

		switch ($this->type) {
			case self::TYPE_SELECT:
			default:
				$sql = $this->getSQLForSelect();
				break;
		}

		$this->generatedSql = $sql;
		return $sql;
	}


	public function getQueryParameters()
	{
		return array_merge(
			(array) $this->args['select'],
			(array) $this->args['from'],
			(array) $this->args['join'],
			(array) $this->args['where'],
			(array) $this->args['group'],
			(array) $this->args['having'],
			(array) $this->args['order']
		);
	}


	private function getSQLForSelect()
	{
		$query =
			'SELECT ' . implode(', ', $this->select)
			. ' FROM ' . $this->getFromClauses()
			. ($this->where !== NULL  ? ' WHERE ' . ($this->where) : '')
			. ($this->group           ? ' GROUP BY ' . implode(', ', $this->group) : '')
			. ($this->having !== NULL ? ' HAVING ' . ($this->having) : '')
			. ($this->order           ? ' ORDER BY ' . implode(', ', $this->order) : '');

		if ($this->limit) {
			$query = $this->driver->modifyLimitQuery($query, $this->limit[0], $this->limit[1]);
		}

		return $query;
	}


	private function getFromClauses()
	{
		$query = $this->from[0] . ($this->from[1] ? " [{$this->from[1]}]" : '');
		foreach ((array) $this->join as $join) {
			$query .= ' '
				. $join['type'] . " JOIN {$join['table']} " . ($join['alias'] ? "[{$join['alias']}] " : '')
				. 'ON (' . $join['on'] . ')';
		}

		return $query;
	}


	public function from($fromExpression, $alias = NULL)
	{
		$this->dirty();
		$this->type = self::TYPE_SELECT;
		$this->from = [$fromExpression, $alias];
		$this->pushArgs('from', array_slice(func_get_args(), 2));
		return $this;
	}


	public function innerJoin($fromAlias, $toExpression, $toAlias, $onExpression)
	{
		return $this->join('INNER', $fromAlias, $toExpression, $toAlias, $onExpression, array_slice(func_get_args(), 4));
	}


	public function leftJoin($fromAlias, $toExpression, $toAlias, $onExpression)
	{
		return $this->join('LEFT', $fromAlias, $toExpression, $toAlias, $onExpression, array_slice(func_get_args(), 4));
	}


	public function rightJoin($fromAlias, $toExpression, $toAlias, $onExpression)
	{
		return $this->join('RIGHT', $fromAlias, $toExpression, $toAlias, $onExpression, array_slice(func_get_args(), 4));
	}


	private function join($type, $fromAlias, $toExpression, $toAlias, $onExpression, $args)
	{
		$this->dirty();
		$this->join[] = [
			'type' => $type,
			'from' => $fromAlias,
			'table' => $toExpression,
			'alias' => $toAlias,
			'on' => $onExpression,
		];
		$this->pushArgs('join', $args);
		return $this;
	}


	public function select($expression)
	{
		$this->dirty();
		$this->select = [$expression];
		$this->args['select'] = [];
		$this->pushArgs('select', array_slice(func_get_args(), 1));
		return $this;
	}


	public function addSelect($expression)
	{
		$this->dirty();
		$this->select[] = $expression;
		$this->pushArgs('select', array_slice(func_get_args(), 1));
		return $this;
	}


	public function where($expression)
	{
		$this->dirty();
		$this->where = $expression;
		$this->args['where'] = [];
		$this->pushArgs('where', array_slice(func_get_args(), 1));
		return $this;
	}


	public function andWhere($expression)
	{
		$this->dirty();
		$this->where = '(' . $this->where . ') AND (' . $expression . ')';
		$this->pushArgs('where', array_slice(func_get_args(), 1));
		return $this;
	}


	public function orWhere($expression)
	{
		$this->dirty();
		$this->where = '(' . $this->where . ') OR (' . $expression . ')';
		$this->pushArgs('where', array_slice(func_get_args(), 1));
		return $this;
	}


	public function groupBy($expression)
	{
		$this->dirty();
		$this->group = [$expression];
		$this->args['group'] = [];
		$this->pushArgs('group', array_slice(func_get_args(), 1));
		return $this;
	}


	public function addGroupBy($expression)
	{
		$this->dirty();
		$this->group[] = $expression;
		$this->pushArgs('group', array_slice(func_get_args(), 1));
		return $this;
	}


	public function having($expression)
	{
		$this->dirty();
		$this->having = $expression;
		$this->args['having'] = [];
		$this->pushArgs('having', array_slice(func_get_args(), 1));
		return $this;
	}


	public function andHaving($expression)
	{
		$this->dirty();
		$this->having = '(' . $this->having . ') AND (' . $expression . ')';
		$this->pushArgs('having', array_slice(func_get_args(), 1));
		return $this;
	}


	public function orHaving($expression)
	{
		$this->dirty();
		$this->having = '(' . $this->having . ') OR (' . $expression . ')';
		$this->pushArgs('having', array_slice(func_get_args(), 1));
		return $this;
	}


	public function orderBy($expression)
	{
		$this->dirty();
		$this->order = [$expression];
		$this->args['order'] = [];
		$this->pushArgs('order', array_slice(func_get_args(), 1));
		return $this;
	}


	public function addOrderBy($expression)
	{
		$this->dirty();
		$this->order[] = $expression;
		$this->pushArgs('order', array_slice(func_get_args(), 1));
		return $this;
	}


	public function limitBy($limit, $offset = NULL)
	{
		$this->dirty();
		$this->limit = [$limit, $offset];
		return $this;
	}


	private function dirty()
	{
		$this->generatedSql = NULL;
	}


	private function pushArgs($type, array $args)
	{
		$this->args[$type] = array_merge((array) $this->args[$type], $args);
	}

}
