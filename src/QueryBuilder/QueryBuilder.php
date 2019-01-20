<?php declare(strict_types = 1);

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

	/** @var IDriver */
	private $driver;

	/** @var int */
	private $type = self::TYPE_SELECT;

	/** @var array */
	private $args = [
		'select' => null,
		'from' => null,
		'join' => null,
		'where' => null,
		'group' => null,
		'having' => null,
		'order' => null,
	];

	/** @var array|null */
	private $select;

	/** @var array|null */
	private $from;

	/** @var array|null */
	private $join;

	/** @var string|null */
	private $where;

	/** @var array|null */
	private $group;

	/** @var string|null */
	private $having;

	/** @var array|null */
	private $order;

	/** @var array|null */
	private $limit;

	/** @var string|null */
	private $generatedSql;


	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
	}


	public function getQuerySql(): string
	{
		if ($this->generatedSql !== null) {
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


	public function getQueryParameters(): array
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


	private function getSqlForSelect(): string
	{
		$query =
			'SELECT ' . ($this->select !== null ? implode(', ', $this->select) : '*')
			. ' FROM ' . $this->getFromClauses()
			. ($this->where !== null  ? ' WHERE ' . ($this->where) : '')
			. ($this->group           ? ' GROUP BY ' . implode(', ', $this->group) : '')
			. ($this->having !== null ? ' HAVING ' . ($this->having) : '')
			. ($this->order           ? ' ORDER BY ' . implode(', ', $this->order) : '');

		if ($this->limit) {
			$query = $this->driver->modifyLimitQuery($query, $this->limit[0], $this->limit[1]);
		}

		return $query;
	}


	private function getFromClauses(): string
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


	public function getClause(string $part): array
	{
		if (!isset($this->args[$part]) && !array_key_exists($part, $this->args)) {
			throw new InvalidArgumentException("Unknown '$part' clause type.");
		}

		return [$this->$part, $this->args[$part]];
	}


	public function from(string $fromExpression, ?string $alias = null, ...$args): self
	{
		$this->dirty();
		$this->type = self::TYPE_SELECT;
		$this->from = [$fromExpression, $alias];
		$this->pushArgs('from', $args);
		return $this;
	}


	public function getFromAlias()
	{
		if ($this->from === null) {
			throw new InvalidStateException('From clause has not been set.');
		}

		return $this->from[1];
	}


	public function innerJoin(string $fromAlias, string $toExpression, string $toAlias, string $onExpression, ...$args): self
	{
		return $this->join('INNER', $fromAlias, $toExpression, $toAlias, $onExpression, $args);
	}


	public function leftJoin(string $fromAlias, string $toExpression, string $toAlias, string $onExpression, ...$args): self
	{
		return $this->join('LEFT', $fromAlias, $toExpression, $toAlias, $onExpression, $args);
	}


	public function rightJoin(string $fromAlias, string $toExpression, string $toAlias, string $onExpression, ...$args): self
	{
		return $this->join('RIGHT', $fromAlias, $toExpression, $toAlias, $onExpression, $args);
	}


	public function getJoin(string $toAlias)
	{
		return isset($this->join[$toAlias]) ? $this->join[$toAlias] : null;
	}


	private function join(string $type, string $fromAlias, string $toExpression, string $toAlias, string $onExpression, array $args): self
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
	 * Sets expression as SELECT clause. Passing null sets clause to the default state.
	 */
	public function select(?string $expression = null, ...$args): self
	{
		$this->dirty();
		$this->select = $expression === null ? null : [$expression];
		$this->args['select'] = $args;
		return $this;
	}


	/**
	 * Adds expression to SELECT clause.
	 */
	public function addSelect(string $expression, ...$args): self
	{
		if (!is_string($expression)) {
			throw new InvalidArgumentException('Select expression has to be a string.');
		}
		$this->dirty();
		$this->select[] = $expression;
		$this->pushArgs('select', $args);
		return $this;
	}


	/**
	 * Sets expression as WHERE clause. Passing null sets clause to the default state.
	 */
	public function where(?string $expression = null, ...$args): self
	{
		$this->dirty();
		$this->where = $expression;
		$this->args['where'] = $args;
		return $this;
	}


	/**
	 * Adds expression with AND to WHERE clause.
	 */
	public function andWhere(string $expression, ...$args): self
	{
		$this->dirty();
		$this->where = $this->where ? '(' . $this->where . ') AND (' . $expression . ')' : $expression;
		$this->pushArgs('where', $args);
		return $this;
	}


	/**
	 * Adds expression with OR to WHERE clause.
	 */
	public function orWhere(string $expression, ...$args): self
	{
		$this->dirty();
		$this->where = $this->where ? '(' . $this->where . ') OR (' . $expression . ')' : $expression;
		$this->pushArgs('where', $args);
		return $this;
	}


	/**
	 * Sets expression as GROUP BY clause. Passing null sets clause to the default state.
	 */
	public function groupBy(?string $expression = null, ...$args): self
	{
		$this->dirty();
		$this->group = $expression === null ? null : [$expression];
		$this->args['group'] = $args;
		return $this;
	}


	/**
	 * Adds expression to GROUP BY clause.
	 */
	public function addGroupBy($expression, ...$args): self
	{
		$this->dirty();
		$this->group[] = $expression;
		$this->pushArgs('group', $args);
		return $this;
	}


	/**
	 * Sets expression as HAVING clause. Passing null sets clause to the default state.
	 */
	public function having(?string $expression = null, ...$args): self
	{
		$this->dirty();
		$this->having = $expression;
		$this->args['having'] = $args;
		return $this;
	}


	/**
	 * Adds expression with AND to HAVING clause.
	 */
	public function andHaving(string $expression, ...$args): self
	{
		$this->dirty();
		$this->having = $this->having ? '(' . $this->having . ') AND (' . $expression . ')' : $expression;
		$this->pushArgs('having', $args);
		return $this;
	}


	/**
	 * Adds expression with OR to HAVING clause.
	 */
	public function orHaving(string $expression, ...$args): self
	{
		$this->dirty();
		$this->having = $this->having ? '(' . $this->having . ') OR (' . $expression . ')' : $expression;
		$this->pushArgs('having', $args);
		return $this;
	}


	/**
	 * Sets expression as ORDER BY clause. Passing null sets clause to the default state.
	 */
	public function orderBy(?string $expression = null, ...$args): self
	{
		$this->dirty();
		$this->order = $expression === null ? null : [$expression];
		$this->args['order'] = $args;
		return $this;
	}


	/**
	 * Adds expression to ORDER BY clause.
	 */
	public function addOrderBy(string $expression, ...$args): self
	{
		$this->dirty();
		$this->order[] = $expression;
		$this->pushArgs('order', $args);
		return $this;
	}


	/**
	 * Sets LIMIT and OFFSET clause.
	 */
	public function limitBy(?int $limit, int $offset = null): self
	{
		$this->dirty();
		$this->limit = $limit || $offset ? [$limit, $offset] : null;
		return $this;
	}


	/**
	 * Returns true if LIMIT or OFFSET clause is set.
	 */
	public function hasLimitOffsetClause(): bool
	{
		return $this->limit !== null;
	}


	/**
	 * Returns limit and offset clause arguments.
	 */
	public function getLimitOffsetClause(): ?array
	{
		return $this->limit;
	}


	private function dirty()
	{
		$this->generatedSql = null;
	}


	private function pushArgs($type, array $args)
	{
		$this->args[$type] = array_merge((array) $this->args[$type], $args);
	}


	/**
	 * @return string[]
	 */
	private function getKnownAliases(): array
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
