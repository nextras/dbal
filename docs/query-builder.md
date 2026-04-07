## Query Builder

Query builder is a tool for constructing SQL queries. It lets you define a query with a fluent API.

#### API

The API is designed to be convenient, safe, and explicit. If an argument is described as an expression, you may use modifiers in that expression and pass their values as the remaining method arguments.

To execute a `QueryBuilder`, pass it to `queryByQueryBuilder()` on the connection. The method returns a `Result` instance.

```php
$builder = $this->connection->createQueryBuilder();
// modify query builder
$result = $this->connection->queryByQueryBuilder($builder);
```

#### FROM clause

Create a `QueryBuilder` instance and set a source table with `from()`. You can pass an alias. The first argument is treated as an SQL expression, so escape identifiers manually with `[]` or use a modifier such as `%table`. The second argument is an optional table alias; the remaining arguments are values for modifiers used in the expression.

```php
// table users, aliased as a
$builder->from('[users]', 'a');

// escaping table name
$builder->from('[orders]', 'o');

// or pass the table name as an argument
$builder->from('%table', 'o', $tableName);

// table as result of stored function/procedure
$builder->from('my_orders(%i, %i)', 'orders', $userId, $groupId);
```

#### WHERE, HAVING and GROUP BY clauses

You can add conditions with `andWhere()` or `orWhere()`. These methods accept an expression as the first argument; all remaining arguments are values for modifiers used in that expression.

```php
$builder->andWhere('[order_id] = %i', $orderId)
	->orWhere('[user_id] IN %i[] AND [group_id] = %i', $userIds, $groupId);

// will produce
// WHERE ([order_id] = 123) OR ([user_id] IN (10, 20) AND [group_id] = 5)
```

`andHaving()` and `orHaving()` have the same signature and behavior as the `where` methods.

`GROUP BY` expressions are not connected with logical operators, so the method is called `addGroupBy()`. It accepts an expression and optional modifier arguments just like the other clause methods.

Let's see an example: depending on `$cond` we build query which will retrieve daily number of issues created/resolved in the last week and filter only days with more than 10 issues.

```php
$column = $cond ? 'created_at' : 'resolved_at';

$builder = $connection->createQueryBuilder();
$builder->select('DATE(%column), COUNT(*)', $column);
$builder->from('[issues]');
$builder->andWhere('%column > NOW() - INTERVAL 1 WEEK', $column);
$builder->addGroupBy('DATE(%column)', $column);
$builder->andHaving('COUNT(*) > 10');
```

Query builder also has `where()`, `groupBy()`, and `having()` variants, which replace any previously defined clause contents:

```php
$builder = $connection->createQueryBuilder();
$builder->from('[issues]');
$builder->where('[created_at] > NOW()');
$builder->where('[created_at] < NOW()'); // replaces the previous condition

// will produce
// SELECT * FROM [issues] WHERE [created_at] < NOW();
```

You can also use these methods to clear the clause.

#### SELECT, ORDER BY and LIMIT clause

The builder provides methods for `SELECT`, `ORDER BY`, and `LIMIT` clauses: `addSelect()`, `select()`, `addOrderBy()`, `orderBy()`, and `limitBy()`. `select()` and `orderBy()` accept modifier arguments in the same way as the clause methods above.

```php
$builder->addSelect('id, %column, [another_escaped_column]', $myColumn);
$builder->addSelect('COALESCE(column)');

$builder->addOrderBy('FIELD(type, %s, %s, %s)', "type1", "type2", "type3");

$builder->limitBy(20); // selects the first 20 results
$builder->limitBy(20, 10); // sets offset to 10
```

#### INNER, LEFT and RIGHT JOIN

Use `joinOnce()` for deduplicated joins, or `addInnerJoin()`, `addLeftJoin()`, and `addRightJoin()` when you want to append another join clause explicitly.

The `add*Join()` methods all have the same signature. Arguments:
- to expression - target expression, do not forget to escape the target table name, you may define also an alias,
- on expression - ON clause expression,
- arguments for expressions.

```php
$builder->from('[authors]', 'a');
$builder->addLeftJoin('[books] AS [b]', '[a.id] = [b.authorId] AND [b.title] = %s', $title);

// will produce
// FROM [authors] AS [a]
// LEFT JOIN [books] AS [b] ON ([a.id] = [b.authorId] AND [b.title] = 'Example title')
```

Use `joinOnce()` if you want the same logical join to be added at most once:

```php
$builder->from('[authors]', 'a');
$builder->joinOnce('LEFT', '[books] AS [b]', '[a.id] = [b.authorId]', []);
```

`joinOnce()` deduplicates joins by the join type, the to-expression, the on-expression and the `$hashSuffix`. The expression arguments are intentionally not part of the hash, so two calls that differ only in their arguments are treated as the same join. This matters when expressions are built with modifiers such as `%table` or `%column`: different parameter values may still share the same SQL expression shape. Pass a distinct `$hashSuffix` to keep such joins apart:

```php
$builder->from('[authors]', 'a');

// each %table placeholder (in the to- and on-expression) consumes one argument,
// hence the table name is passed twice per join
$builder->joinOnce('LEFT', '%table', '%table.authorId = [a.id]', ['book_tags', 'book_tags'], 'book_tags');
$builder->joinOnce('LEFT', '%table', '%table.authorId = [a.id]', ['author_tags', 'author_tags'], 'author_tags');

// will produce two distinct joins:
// LEFT JOIN [book_tags] ON ([book_tags].authorId = [a.id])
// LEFT JOIN [author_tags] ON ([author_tags].authorId = [a.id])
```

#### INDEX HINTS (MySQL)

Use `indexHints()` to pass MySQL index hints to the query planner.

```php
$builder->from('[authors]', 'a');
$builder->indexHints('FORCE INDEX(%column)', 'my_index_name');

// will produce
// ... FROM [authors] AS [a] FORCE INDEX (`my_index_name`) ...
```
