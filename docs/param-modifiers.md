## Modifiers

Dbal lets you safely build SQL queries. It provides these parameter modifiers:

| Modifier                                   | Type           | Description                                                                                                                      |
|--------------------------------------------|----------------|----------------------------------------------------------------------------------------------------------------------------------|
| `%s`, `%?s`, `%s[]`, `%...s[]`             | string         | not nullable, nullable, array of                                                                                                 |
| `%i`, `%?i`, `%i[]`, `%...i[]`             | integer        | not nullable, nullable, array of                                                                                                 |
| `%f`, `%?f`, `%f[]`, `%...f[]`             | float          | not nullable, nullable, array of                                                                                                 |
| `%b`, `%?b`, `%b[]`, `%...b[]`             | boolean        | not nullable, nullable, array of                                                                                                 |
| `%dt`, `%?dt`, `%dt[]`, `%...dt[]`         | datetime       | not nullable, nullable, array of<br>read more about [datetime handling](datetime); using wrong modifier may damage your data     |
| `%ldt`, `%?ldt`, `%ldt[]`, `%...ldt[]`     | local datetime | datetime without timezone conversion<br>read more about [datetime handling](datetime); using wrong modifier may damage your data |
| `%ld`, `%?ld`, `%ld[]`, `%...ld[]`         | local date     | a date; pass DateTimeInterface instance, DBAL will pick just the date nevertheless the time or timezone                          |
| `%di`, `%?di`, `%di[]`, `%...di[]`         | date interval  | DateInterval instance                                                                                                            |
| `%blob`, `%?blob`, `%blob[]`               | binary string  | not nullable, nullable, array of                                                                                                 |
| `%json`, `%?json`, `%json[]`, `%...json[]` | any            | not nullable, nullable, array of                                                                                                 |
| `%any`                                     | any            | any value                                                                                                                        |
| `%_like`, `%like_`, `%_like_`              | string         | like left, like right, like both sides                                                                                           |

All modifiers require an argument of the specific data type. For example, `%f` accepts only floats and integers.

```php
$connection->query('id = %i AND name IN (%?s, %?s)', 1, NULL, 'foo');
// `id` = 1 AND name IN (NULL, 'foo')

$connection->query('name LIKE %_like_', $query);
// escapes query and adds % to both sides
// name LIKE '%escaped query expression%'
```

Array modifiers accept arrays of the required type. The basic `[]` suffix denotes such an array and Dbal also adds wrapping parentheses. If you need to omit them for more complex SQL, use the spread variant by adding three dots after the `%` character.

```php
$connection->query('WHERE id IN %i[]', [1, 3, 4]);
// WHERE `id` IN (1, 3, 4)

$connection->query('WHERE [roles.privileges] ?| ARRAY[%...s[]]', ['backend', 'frontend']);
// WHERE "roles"."privileges" ?| ARRAY['backend', 'frontend']
```

Other available modifiers:

| Modifier               | Description                                                                                                                                                                                                                                              |
|------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `%and`                 | AND condition                                                                                                                                                                                                                                            |
| `%or`                  | OR condition                                                                                                                                                                                                                                             |
| `%multiOr`             | OR condition with multiple conditions in pairs                                                                                                                                                                                                           |
| `%values`, `%values[]` | expands array for INSERT clause, multi insert                                                                                                                                                                                                            |
| `%set`                 | expands array for SET clause                                                                                                                                                                                                                             |
| `%table`, `%table[]`   | escapes string as table name, may contain a database or schema name separated by a dot; surrounding parentheses are not added to `%table[]` modifier; `%table` supports formatting a `Nextras\Dbal\Platforms\Data\Fqn` instance.                         |
| `%column`, `%column[]` | escapes string as column name, may contain a database name, schema name or asterisk (`*`) separated by a dot; surrounding parentheses are not added to `%column[]` modifier; `%column` supports formatting a `Nextras\Dbal\Platforms\Data\Fqn` instance. |
| `%ex`                  | expands array as processor arguments                                                                                                                                                                                                                     |
| `%raw`                 | inserts string argument as is                                                                                                                                                                                                                            |
| `%%`                   | escapes to single `%` (useful in `date_format()`, etc.)                                                                                                                                                                                                  |
| `[[`, `]]`             | escapes to single `[` or `]` (useful when working with array, etc.)                                                                                                                                                                                      |

Let's examine `%and` and `%or` behavior. If an array key is numeric and its value is an array, the value is expanded with the `%ex` modifier. If the first value in this array is an `Fqn` instance, the resulting SQL is constructed similarly to a key-value array; the modifier is an optional string on the second index. See the examples below.

```php
$connection->query('%and', [
	'city' => 'Winterfell',
	'age'  => 23,
]);
// `city` = 'Winterfell' AND `age` = 23


$connection->query('%or', [
	'city' => 'Winterfell',
	'age'  => [23, 25],
]);
// `city` = 'Winterfell' OR `age` IN (23, 25)


$connection->query('%or', [
	'city' => 'Winterfell',
	['[age] IN %i[]', [23, 25]],
]);
// `city` = 'Winterfell' OR `age` IN (23, 25)

$connection->query('%or', [
    [new Fqn(schema: '', name: 'city'), 'Winterfell'],
    [new Fqn(schema: '', name: 'age'), [23, 25], '%i[]'],
]);
// `city` = 'Winterfell' OR `age` IN (23, 25)
```

If you want to select multiple rows with a combined condition for each row, you may use a multi-column `IN` expression. Some databases do not support this feature, so Dbal provides the universal `%multiOr` modifier, which falls back to an expanded `OR` expression when needed. The `%multiOr` modifier supports an optional modifier appended to the column name; if you use it, it has to be set for all entries.

```php
$connection->query('%multiOr', [
	['tag_id%i' => 1, 'book_id' => 23],
	['tag_id%i' => 4, 'book_id' => 12],
	['tag_id%i' => 9, 'book_id' => 83],
]);
// MySQL or PostgreSQL
// (tag_id, book_id) IN ((1, 23), (4, 12), (9, 83))

// SQL Server
// (tag_id = 1 AND book_id = 23) OR (tag_id = 4 AND book_id = 12) OR (tag_id = 9 AND book_id = 83)
```

Alternatively, if you need to pass the column name as `Fqn` instance, use a data format where the array consists of list columns, then the list of values and optional list of modifiers.

```php
$aFqn = new Fqn('tbl', 'tag_id');
$bFqn = new Fqn('tbl', 'book_id');
$connection->query('%multiOr', [
    [[$aFqn, 1, '%i'], [$bFqn, 23]],
    [[$aFqn, 4, '%i'], [$bFqn, 12]],
    [[$aFqn, 9, '%i'], [$bFqn, 83]],
]);

// MySQL or PostgreSQL
// (tbl.tag_id, tbl.book_id) IN ((1, 23), (4, 12), (9, 83))

// SQL Server
// (tbl.tag_id = 1 AND tbl.book_id = 23) OR (tbl.tag_id = 4 AND tbl.book_id = 12) OR (tbl.tag_id = 9 AND tbl.book_id = 83)
```

Examples of inserting and updating:

```php
$connection->query('INSERT INTO [users] %values', [
    'name' => 'Jon Snow'
]);
// INSERT INTO `users` (`name`) VALUES ('Jon Snow')


$connection->query('INSERT INTO [users] %values[]', [
    ['name' => 'Jon Snow'],
    ['name' => 'The Imp'],
]);
// INSERT INTO `users` (`name`) VALUES ('Jon Snow'), ('The Imp')


$connection->query('UPDATE [users] SET %set WHERE [id] = %i', [
    'name' => 'Jon Snow'
], 1);
// UPDATE `users` SET `name` = 'Jon Snow' WHERE `id` = 1
```

`%ex` modifier expands passed array as arguments of new `query()` method call.

```php
$connection->query('%ex', ['id = %i', 1]);
// equals to
$connection->query('id = %i', 1);
```


### Custom Modifiers

You may add support for your own modifier. To do that, create a custom `ISqlProcessorFactory` and register the modifier with `setCustomModifier()`:

```php
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\SqlProcessor;

class SqlProcessorFactory implements ISqlProcessorFactory
{
	public function create(IConnection $connection): SqlProcessor
	{
		$processor = new SqlProcessor($connection->getPlatform());
		$processor->setCustomModifier(
			'mybool',
			function (SqlProcessor $processor, mixed $value): string {
				return $processor->processModifier('s', $value ? 'yes' : 'no');
			}
		);
		return $processor;
	}
}
```

Use `sqlProcessorFactory` configuration key to pass a factory instance. See configuration chapters.

### Modifier Resolver

`SqlProcessor` also allows setting a custom modifier resolver for values passed through implicit or explicit `%any`. This lets you introduce custom processing for your own types. For safety reasons, only `%any` can be overridden this way. Implement `ISqlProcessorModifierResolver`, return the modifier name for the passed value, and register the resolver in `SqlProcessor`.

```php
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ISqlProcessorModifierResolver;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\SqlProcessor;

class BrickSqlProcessorModifierResolver implements ISqlProcessorModifierResolver
{
    public function resolve($value): ?string
    {
        if ($value instanceof \Brick\DayOfWeek) {
            return 'brickDayOfWeek';
        }
        return null;
    }
}

class SqlProcessorFactory implements ISqlProcessorFactory
{
	public function create(IConnection $connection): SqlProcessor
	{
		$processor = new SqlProcessor($connection->getPlatform());
		$processor->setCustomModifier(
		    'brickDayOfWeek',
		    function (SqlProcessor $processor, mixed $value): string {
		        assert($value instanceof \Brick\DayOfWeek);
			    return $processor->processModifier('s', $value->getValue());
		    }
		);
		$processor->addModifierResolver(new BrickSqlProcessorModifierResolver());
		return $processor;
	}
}
```
