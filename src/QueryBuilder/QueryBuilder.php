<?php declare(strict_types = 1);

namespace Nextras\Dbal\QueryBuilder;


use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function trigger_error;


class QueryBuilder
{
	use StrictObjectTrait;


	/** @var IDriver */
	private $driver;

	/**
	 * @var array
	 * @phpstan-var array<string, array<mixed>|null>
	 */
	private $args = [
		'select' => null,
		'from' => null,
		'indexHints' => null,
		'join' => null,
		'where' => null,
		'group' => null,
		'having' => null,
		'order' => null,
	];

	/** @var string[]|null */
	private $select;

	/**
	 * @var array|null
	 * @phpstan-var array{string, ?string}|null
	 */
	private $from;

	/** @var string|null */
	private $indexHints;

	/**
	 * @var array|null
	 * @phpstan-var array<array{type: string, from: string, alias: string, table: string, on: string}>|null
	 */
	private $join;

	/** @var string|null */
	private $where;

	/** @var string[]|null */
	private $group;

	/** @var string|null */
	private $having;

	/** @var string[]|null */
	private $order;

	/**
	 * @var array|null
	 * @phpstan-var array{?int, ?int}|null
	 */
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

		$this->generatedSql = $this->getSqlForSelect();
		return $this->generatedSql;
	}


	/**
	 * @phpstan-return array<mixed>
	 */
	public function getQueryParameters(): array
	{
		return array_merge(
			(array) $this->args['select'],
			(array) $this->args['from'],
			(array) $this->args['indexHints'],
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
			. ($this->where !== null ? ' WHERE ' . ($this->where) : '')
			. ($this->group !== null ? ' GROUP BY ' . implode(', ', $this->group) : '')
			. ($this->having !== null ? ' HAVING ' . ($this->having) : '')
			. ($this->order !== null ? ' ORDER BY ' . implode(', ', $this->order) : '');

		if ($this->limit !== null) {
			$query = $this->driver->modifyLimitQuery($query, $this->limit[0], $this->limit[1]);
		}

		return $query;
	}


	private function getFromClauses(): string
	{
		if ($this->from === null) {
			throw new InvalidStateException();
		}
		$query = $this->from[0] . ($this->from[1] !== null ? " AS [{$this->from[1]}]" : '');

		if ($this->indexHints !== null) {
			$query .= ' ' . $this->indexHints;
		}

		foreach ((array) $this->join as $join) {
			$query .= " $join[type] JOIN $join[table] ON ($join[on])";
		}

		return $query;
	}


	/**
	 * @phpstan-return array{mixed, mixed}
	 */
	public function getClause(string $part): array
	{
		if (!isset($this->args[$part]) && !array_key_exists($part, $this->args)) {
			throw new InvalidArgumentException("Unknown '$part' clause type.");
		}

		return [$this->$part, $this->args[$part]]; // @phpstan-ignore-line
	}


	/**
	 * @phpstan-param array<int, mixed> $args
	 */
	public function from(string $fromExpression, ?string $alias = null, ...$args): self
	{
		$this->dirty();
		$this->from = [$fromExpression, $alias];
		$this->pushArgs('from', $args);
		return $this;
	}


	/**
	 * MySQL only feature.
	 * @phpstan-param array<int, mixed> $args
	 * @return static
	 */
	public function indexHints(?string $indexHintsExpression, ...$args): self
	{
		$this->dirty();
		$this->indexHints = $indexHintsExpression;
		$this->pushArgs('indexHints', $args);
		return $this;
	}


	public function getFromAlias(): ?string
	{
		if ($this->from === null) {
			throw new InvalidStateException('From clause has not been set.');
		}

		return $this->from[1];
	}


	/**
	 * @phpstan-param array<int, mixed> $args
	 * @deprecated QueryBuilder::innerJoin() is deprecated. Use QueryBuilder::joinInner() without $fromAlias and with $toAlias included in $toExpression.
	 * @noinspection  PhpUnusedParameterInspection
	 */
	public function innerJoin(
		string $fromAlias,
		string $toExpression,
		string $toAlias,
		string $onExpression,
		...$args
	): self
	{
		trigger_error(
			'QueryBuilder::innerJoin() is deprecated. Use QueryBuilder::joinInner() without $fromAlias and with $toAlias included in $toExpression.',
			E_USER_DEPRECATED
		);
		return $this->joinInner("$toExpression AS [$toAlias]", $onExpression, $args);
	}


	/**
	 * @phpstan-param array<int, mixed> $args
	 * @deprecated QueryBuilder::leftJoin() is deprecated. Use QueryBuilder::joinLeft() without $fromAlias and with $toAlias included in $toExpression.
	 * @noinspection  PhpUnusedParameterInspection
	 */
	public function leftJoin(
		string $fromAlias,
		string $toExpression,
		string $toAlias,
		string $onExpression,
		...$args
	): self
	{
		trigger_error(
			'QueryBuilder::leftJoin() is deprecated. Use QueryBuilder::joinLeft() without $fromAlias and with $toAlias included in $toExpression.',
			E_USER_DEPRECATED
		);
		return $this->joinLeft("$toExpression AS [$toAlias]", $onExpression, $args);
	}


	/**
	 * @phpstan-param array<int, mixed> $args
	 * @deprecated QueryBuilder::rightJoin() is deprecated. Use QueryBuilder::joinRight() without $fromAlias and with $toAlias included in $toExpression.
	 * @noinspection  PhpUnusedParameterInspection
	 */
	public function rightJoin(
		string $fromAlias,
		string $toExpression,
		string $toAlias,
		string $onExpression,
		...$args
	): self
	{
		trigger_error(
			'QueryBuilder::rightJoin() is deprecated. Use QueryBuilder::joinRight() without $fromAlias and with $toAlias included in $toExpression.',
			E_USER_DEPRECATED
		);
		return $this->joinRight("$toExpression AS [$toAlias]", $onExpression, $args);
	}


	/**
	 * @phpstan-param array<int, mixed> $args
	 */
	public function joinInner(string $toExpression, string $onExpression, ...$args): self
	{
		return $this->join('INNER', $toExpression, $onExpression, $args);
	}


	/**
	 * @phpstan-param array<int, mixed> $args
	 */
	public function joinLeft(string $toExpression, string $onExpression, ...$args): self
	{
		return $this->join('LEFT', $toExpression, $onExpression, $args);
	}


	/**
	 * @phpstan-param array<int, mixed> $args
	 */
	public function joinRight(string $toExpression, string $onExpression, ...$args): self
	{
		return $this->join('RIGHT', $toExpression, $onExpression, $args);
	}


	public function removeJoins(): self
	{
		$this->join = null;
		$this->args['join'] = null;
		return $this;
	}


	/**
	 * @phpstan-param array<mixed> $args
	 */
	private function join(string $type, string $toExpression, string $onExpression, array $args): self
	{
		$this->dirty();
		$this->join[$toExpression] = [
			'type' => $type,
			'table' => $toExpression,
			'on' => $onExpression,
		];
		$this->pushArgs('join', $args);
		return $this;
	}


	/**
	 * Sets expression as SELECT clause. Passing null sets clause to the default state.
	 * @phpstan-param array<int, mixed> $args
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
	 * @phpstan-param array<int, mixed> $args
	 */
	public function addSelect(string $expression, ...$args): self
	{
		$this->dirty();
		$this->select[] = $expression;
		$this->pushArgs('select', $args);
		return $this;
	}


	/**
	 * Sets expression as WHERE clause. Passing null sets clause to the default state.
	 * @phpstan-param array<int, mixed> $args
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
	 * @phpstan-param array<int, mixed> $args
	 */
	public function andWhere(string $expression, ...$args): self
	{
		$this->dirty();
		$this->where = $this->where !== null ? '(' . $this->where . ') AND (' . $expression . ')' : $expression;
		$this->pushArgs('where', $args);
		return $this;
	}


	/**
	 * Adds expression with OR to WHERE clause.
	 * @phpstan-param array<int, mixed> $args
	 */
	public function orWhere(string $expression, ...$args): self
	{
		$this->dirty();
		$this->where = $this->where !== null ? '(' . $this->where . ') OR (' . $expression . ')' : $expression;
		$this->pushArgs('where', $args);
		return $this;
	}


	/**
	 * Sets expression as GROUP BY clause. Passing null sets clause to the default state.
	 * @phpstan-param array<int, mixed> $args
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
	 * @phpstan-param array<int, mixed> $args
	 */
	public function addGroupBy(string $expression, ...$args): self
	{
		$this->dirty();
		$this->group[] = $expression;
		$this->pushArgs('group', $args);
		return $this;
	}


	/**
	 * Sets expression as HAVING clause. Passing null sets clause to the default state.
	 * @phpstan-param array<int, mixed> $args
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
	 * @phpstan-param array<int, mixed> $args
	 */
	public function andHaving(string $expression, ...$args): self
	{
		$this->dirty();
		$this->having = $this->having !== null ? '(' . $this->having . ') AND (' . $expression . ')' : $expression;
		$this->pushArgs('having', $args);
		return $this;
	}


	/**
	 * Adds expression with OR to HAVING clause.
	 * @phpstan-param array<int, mixed> $args
	 */
	public function orHaving(string $expression, ...$args): self
	{
		$this->dirty();
		$this->having = $this->having !== null ? '(' . $this->having . ') OR (' . $expression . ')' : $expression;
		$this->pushArgs('having', $args);
		return $this;
	}


	/**
	 * Sets expression as ORDER BY clause. Passing null sets clause to the default state.
	 * @phpstan-param array<int, mixed> $args
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
	 * @phpstan-param array<int, mixed> $args
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
	public function limitBy(?int $limit, ?int $offset = null): self
	{
		$this->dirty();
		$this->limit = $limit !== null || $offset !== null ? [$limit, $offset] : null;
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
	 * @phpstan-return array{?int, ?int}|null
	 */
	public function getLimitOffsetClause(): ?array
	{
		return $this->limit;
	}


	private function dirty(): void
	{
		$this->generatedSql = null;
	}


	/**
	 * @phpstan-param array<mixed> $args
	 */
	private function pushArgs(string $type, array $args): void
	{
		$this->args[$type] = array_merge((array) $this->args[$type], $args);
	}
}
