## Query Builder

Query builder is a tool for constructing SQL query. It allows you to define SQL query with fluent API.

#### API

The API is designed to be convenient and yet safe and helpful. Therefore, every method accepts specific arguments and if any of them is suffixed "expression", the method also allow to pass a modifier it this argument. The last argument(s) of every method is then these values for the used modifiers in the expressions.

To get query in QueryBuilder executed, pass the builder to `queryByQueryBuilder()` method on connection, you will receive a `Result` instance.

```php
$builder = $this->connection->createQueryBuilder();
// modify query builder
$result = $this->connection->queryByQueryBuilder($builder);
```

#### FROM clause

Let's create a Query builder instance. Set a source table name by `from()` method. You can pass an alias. The first argument is considered as a SQL expression, you should escape its table name manually with `[]` brackets, or use a modifier. The second argument is an optional table alias, other arguments are arguments for used modifiers.

```php
// table users, aliased a
$builder->from('users', 'a');

// escaping table name
$builder->from('[orders]', 'o');

// or pass it as argument
$builder->from('%table', 'o', $tableName);

// table as result of stored function/procedure
$builder->from('my_orders(%i, %i)', 'orders', $userId, $groupId);
```

#### WHERE, HAVING and GROUP BY clauses

You can add conditions using `andWhere()` or `orWhere()` methods. `*Where` methods accept query expression, all other function arguments are optional and are considered as arguments for modifiers passed in the expression.

```php
$builder->andWhere('order_id = %i', $oderId);
        ->orWhere('user_id IN %i[] AND group_id = %i', $userIds, $groupId);

// will produce
// WHERE (order_id = %i) OR (user_id IN %i[] AND group_id = %i)
```

Methods `andHaving()` and `orHaving()` have the same signature and logic as where methods.

Group by expressions are not connected with logic operators, therefore the method is called `addGroupBy()`. The group-by method has signature as others, accepts an expression and optional arguments.

Let's see an example: depending on `$cond` we build query which will retrieve daily number of issues created/resolved in the last week and filter only days with more than 10 issues.

```php
$column = $cond ? 'created_at' : 'resolved_at';

$builder = $connection->createQueryBuilder();
$builder->select('DATE(%column), COUNT(*)', $column);
$builder->from('issues');
$builder->andWhere('%column > NOW() - INTERVAL 1 WEEK', $column);
$builder->addGroupBy('DATE(%column)', $column);
$builder->andHaving('COUNT(*) > 10');
```

Query builder has alternative methods named `where()`, `groupBy()` and `having()` which remove previously defined conditions and add new one:

```php
$builder = $connection->createQueryBuilder();
$builder->from('issues');
$builder->where('created_at > NOW()');
$builder->where('created_at < NOW()'); // replace previous conditions

// will produce
// SELECT * FROM issues WHERE created_at < NOW();
```

You can also use these methods to empty the clause.

#### SELECT, ORDER BY and LIMIT clause

Builder define methods for select, order by and limit clauses. Use appropriate methods: `addSelect()`, `select()`, `addOrderBy()`, `orderBy()`, and `limitBy()`. Select and order by methods accept modifier arguments as aforementioned methods.

```php
$builder->addSelect('id, %column, [another_escaped_column]', $myColumn);
$builder->addSelect('COALESCE(column)');

$builder->addOrderBy('FIELD(type, %s, %s, %s)', "type1", "type2", "type3");

$builder->limitBy(20); // selects the first 20 results
$builder->limitBy(20, 10); // sets offset to 1O
```

#### INNER, LEFT and RIGHT JOIN

Choose from `joinInner()`, `joinLeft()`, and `joinRight()` methods. Each of them has the same signature. Arguments:
- to expression - target expression, do not forget to escape the target table name, you may define also an alias,
- on expression - ON clause expression,
- arguments for expressions.

```php
$builder->from('[authors]', 'a');
$builder->joinLeft('[books] AS [b]', '[a.id] = [b.authorId] AND [b.title] = %s', $title);

// will produce
// FROM [authors] AS [a]
// LEFT JOIN [books] AS [b] ON ([a.id] = [b.authorId] AND [b.title] = %s)
```

#### INDEX HINTS (MySQL)

Use `indexHints()` method to tell the query planner how to efficiently execute your query.

```php
$builder->from('[authors]', 'a');
$builder->indexHints('FORCE INDEX(%column)', 'my_index_name');

// will produce
// ... FROM [authors] AS [a] FORCE INDEX (`my_index_name`) ...
```
