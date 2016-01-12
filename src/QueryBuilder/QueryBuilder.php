<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\QueryBuilder;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\InvalidArgumentException;
use Nextras\Dbal\InvalidStateException;


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
	private $select;

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


	public function getQuerySql()
	{
		if ($this->generatedSql !== NULL) {
			return $this->generatedSql;
		}

		switch ($this->type) {
			case self::TYPE_SELECT:
			default:
				$sql = $this->getSqlForSelect();
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


	private function getSqlForSelect()
	{
		$query =
			'SELECT ' . ($this->select !== NULL ? implode(', ', $this->select) : '*')
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
		$knownAliases = array_flip($this->getKnownAliases());

		$query = $this->from[0] . ($this->from[1] ? " AS [{$this->from[1]}]" : '');
		foreach ((array) $this->join as $join) {
			if (!isset($knownAliases[$join['from']])) {
				throw new InvalidStateException("Unknown alias '{$join['from']}'.");
			}

			$query .= ' '
				. $join['type'] . " JOIN {$join['table']} " . ($join['alias'] ? "AS [{$join['alias']}] " : '')
				. 'ON (' . $join['on'] . ')';
		}

		return $query;
	}


	public function getClause($part)
	{
		if (!isset($this->args[$part]) && !array_key_exists($part, $this->args)) {
			throw new InvalidArgumentException("Unknown '$part' clause type.");
		}

		return [$this->$part, $this->args[$part]];
	}


	public function from($fromExpression, $alias = NULL)
	{
		$this->dirty();
		$this->type = self::TYPE_SELECT;
		$this->from = [$fromExpression, $alias];
		$this->pushArgs('from', array_slice(func_get_args(), 2));
		return $this;
	}


	public function getFromAlias()
	{
		if ($this->from === NULL) {
			throw new InvalidStateException('From clause has not been set.');
		}

		return $this->from[1];
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


	public function getJoin($toAlias)
	{
		return isset($this->join[$toAlias]) ? $this->join[$toAlias] : NULL;
	}


	private function join($type, $fromAlias, $toExpression, $toAlias, $onExpression, $args)
	{
		$this->dirty();
		$this->join[$toAlias] = [
			'type' => $type,
			'from' => $fromAlias,
			'table' => $toExpression,
			'alias' => $toAlias,
			'on' => $onExpression,
		];
		$this->pushArgs('join', $args);
		return $this;
	}


	/**
	 * Sets expression as SELECT clause. Passing NULL sets clause to the default state.
	 * @param  string|NULL $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function select($expression)
	{
		if (!($expression === NULL || is_string($expression))) {
			throw new InvalidArgumentException('Select expression has to be a string or NULL.');
		}
		$this->dirty();
		$this->select = $expression === NULL ? NULL : [$expression];
		$this->args['select'] = array_slice(func_get_args(), 1);
		return $this;
	}


	/**
	 * Adds expression to SELECT clause.
	 * @param  string $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function addSelect($expression)
	{
		if (!is_string($expression)) {
			throw new InvalidArgumentException('Select expression has to be a string.');
		}
		$this->dirty();
		$this->select[] = $expression;
		$this->pushArgs('select', array_slice(func_get_args(), 1));
		return $this;
	}


	/**
	 * Sets expression as WHERE clause. Passing NULL sets clause to the default state.
	 * @param  string|NULL $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function where($expression)
	{
		if (!($expression === NULL || is_string($expression))) {
			throw new InvalidArgumentException('Where expression has to be a string or NULL.');
		}
		$this->dirty();
		$this->where = $expression;
		$this->args['where'] = array_slice(func_get_args(), 1);
		return $this;
	}


	/**
	 * Adds expression with AND to WHERE clause.
	 * @param  string $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function andWhere($expression)
	{
		if (!is_string($expression)) {
			throw new InvalidArgumentException('Where expression has to be a string.');
		}
		$this->dirty();
		$this->where = $this->where ? '(' . $this->where . ') AND (' . $expression . ')' : $expression;
		$this->pushArgs('where', array_slice(func_get_args(), 1));
		return $this;
	}


	/**
	 * Adds expression with OR to WHERE clause.
	 * @param  string $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function orWhere($expression)
	{
		if (!is_string($expression)) {
			throw new InvalidArgumentException('Where expression has to be a string.');
		}
		$this->dirty();
		$this->where = $this->where ? '(' . $this->where . ') OR (' . $expression . ')' : $expression;
		$this->pushArgs('where', array_slice(func_get_args(), 1));
		return $this;
	}


	/**
	 * Sets expression as GROUP BY clause. Passing NULL sets clause to the default state.
	 * @param  string|NULL $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function groupBy($expression)
	{
		if (!($expression === NULL || is_string($expression))) {
			throw new InvalidArgumentException('Group by expression has to be a string or NULL.');
		}
		$this->dirty();
		$this->group = $expression === NULL ? NULL : [$expression];
		$this->args['group'] = array_slice(func_get_args(), 1);
		return $this;
	}


	/**
	 * Adds expression to GROUP BY clause.
	 * @param  string $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function addGroupBy($expression)
	{
		if (!is_string($expression)) {
			throw new InvalidArgumentException('Group by expression has to be a string.');
		}
		$this->dirty();
		$this->group[] = $expression;
		$this->pushArgs('group', array_slice(func_get_args(), 1));
		return $this;
	}


	/**
	 * Sets expression as HAVING clause. Passing NULL sets clause to the default state.
	 * @param  string|NULL $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function having($expression)
	{
		if (!($expression === NULL || is_string($expression))) {
			throw new InvalidArgumentException('Having expression has to be a string or NULL.');
		}
		$this->dirty();
		$this->having = $expression;
		$this->args['having'] = array_slice(func_get_args(), 1);
		return $this;
	}


	/**
	 * Adds expression with AND to HAVING clause.
	 * @param  string $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function andHaving($expression)
	{
		if (!is_string($expression)) {
			throw new InvalidArgumentException('Having expression has to be a string.');
		}
		$this->dirty();
		$this->having = $this->having ? '(' . $this->having . ') AND (' . $expression . ')' : $expression;
		$this->pushArgs('having', array_slice(func_get_args(), 1));
		return $this;
	}


	/**
	 * Adds expression with OR to HAVING clause.
	 * @param  string $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function orHaving($expression)
	{
		if (!is_string($expression)) {
			throw new InvalidArgumentException('Having expression has to be a string.');
		}
		$this->dirty();
		$this->having = $this->having ? '(' . $this->having . ') OR (' . $expression . ')' : $expression;
		$this->pushArgs('having', array_slice(func_get_args(), 1));
		return $this;
	}


	/**
	 * Sets expression as ORDER BY clause. Passing NULL sets clause to the default state.
	 * @param  string|NULL $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function orderBy($expression)
	{
		if (!($expression === NULL || is_string($expression))) {
			throw new InvalidArgumentException('Order by expression has to be a string or NULL.');
		}
		$this->dirty();
		$this->order = $expression === NULL ? NULL : [$expression];
		$this->args['order'] = array_slice(func_get_args(), 1);
		return $this;
	}


	/**
	 * Adds expression to ORDER BY clause.
	 * @param  string $expression
	 * @param  mixed ...$arg
	 * @return self
	 */
	public function addOrderBy($expression)
	{
		if (!is_string($expression)) {
			throw new InvalidArgumentException('Order by expression has to be a string.');
		}
		$this->dirty();
		$this->order[] = $expression;
		$this->pushArgs('order', array_slice(func_get_args(), 1));
		return $this;
	}


	/**
	 * Sets LIMIT and OFFSET clause.
	 * @param  int|NULL $limit
	 * @param  int|NULL $offset
	 * @return self
	 */
	public function limitBy($limit, $offset = NULL)
	{
		$this->dirty();
		$this->limit = $limit || $offset ? [$limit, $offset] : NULL;
		return $this;
	}


	/**
	 * Returns true if LIMIT or OFFSET clause is set.
	 * @return bool
	 */
	public function hasLimitOffsetClause()
	{
		return $this->limit !== NULL;
	}


	/**
	 * Returns limit and offset clause arguments.
	 * @return array|NULL
	 */
	public function getLimitOffsetClause()
	{
		return $this->limit;
	}


	private function dirty()
	{
		$this->generatedSql = NULL;
	}


	private function pushArgs($type, array $args)
	{
		$this->args[$type] = array_merge((array) $this->args[$type], $args);
	}


	/**
	 * @return string[]
	 */
	private function getKnownAliases()
	{
		$knownAliases = [];
		if (isset($this->from)) {
			$knownAliases[] = isset($this->from[1]) ? $this->from[1] : $this->from[0];
		}
		foreach ((array) $this->join as $join) {
			$knownAliases[] = isset($join['alias']) ? $join['alias'] : $join['table'];
		}

		return $knownAliases;
	}
}
