## Modifiers

Dbal allows you to escape and build safe SQL query. It provides these powerful parameter modifiers:

| Modifier                                   | Type           | Description                                                                                                                       |
|--------------------------------------------|----------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `%s`, `%?s`, `%s[]`, `%...s[]`             | string         | not nullable, nullable, array of                                                                                                  |
| `%i`, `%?i`, `%i[]`, `%...i[]`             | integer        | not nullable, nullable, array of                                                                                                  |
| `%f`, `%?f`, `%f[]`, `%...f[]`             | float          | not nullable, nullable, array of                                                                                                  |
| `%b`, `%?b`, `%b[]`, `%...b[]`             | boolean        | not nullable, nullable, array of                                                                                                  |
| `%dt`, `%?dt`, `%dt[]`, `%...dt[]`         | datetime       | not nullable, nullable, array of<br>read more about [datetime handling](datetime); using wrong modifier may damage your data      |
| `%ldt`, `%?ldt`, `%ldt[]`, `%...ldt[]`     | local datetime | datetime without timezone conversion<br>read more about [datetime handling](datetime);  using wrong modifier may damage your data |
| `%di`, `%?di`, `%di[]`, `%...di[]`         | date interval  | DateInterval instance                                                                                                             |
| `%blob`, `%?blob`, `%blob[]`               | binary string  | not nullable, nullable, array of                                                                                                  |
| `%json`, `%?json`, `%json[]`, `%...json[]` | any            | not nullable, nullable, array of                                                                                                  |
| `%any             `                        |                | any value                                                                                                                         |
| `%_like`, `%like_`, `%_like_`              | string         | like left, like right, like both sides                                                                                            |

All modifiers require an argument of the specific data type - e.g. `%f` accepts only floats and integers.

```php
$connection->query('id = %i AND name IN (%?s, %?s)', 1, NULL, 'foo');
// `id` = 1 AND name IN (NULL, 'foo')

$connection->query('name LIKE %_like_', $query);
// escapes query and adds % to both sides
// name LIKE '%escaped query expression%'
```

Array modifiers are able to process array of the required type. The basic `[]` suffix syntax denotes such array. This way Dbal also adds wrapping parenthesis. You may want to omit them for more complex SQL. To do so, use a "spread" variant of array operator -- add three dots after the `%` character.

```php
$connection->query('WHERE id IN %i[]', [1, 3, 4]);
// WHERE `id` IN (1, 3, 4)

$connection->query('WHERE [roles.privileges] ?| ARRAY[%...s[]]', ['backend', 'frontend']);
// WHERE "roles"."privileges" ?| ARRAY['backend', 'frontend']
```

Other available modifiers:

| Modifier               | Description                                                                                                                                                                                                                           |
|------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `%and`                 | AND condition                                                                                                                                                                                                                         |
| `%or`                  | OR condition                                                                                                                                                                                                                          |
| `%multiOr`             | OR condition with multiple conditions in pairs                                                                                                                                                                                        |
| `%values`, `%values[]` | expands array for INSERT clause, multi insert                                                                                                                                                                                         |
| `%set`                 | expands array for SET clause                                                                                                                                                                                                          |
| `%table`, `%table[]`   | escapes string as table name, may contain a database or schema name separated by a dot; surrounding parentheses are not added to `%table[]` modifier; `%table` supports also processing a `Nextras\Dbal\Platforms\Data\Fqn` instance. |
| `%column`, `%column[]` | escapes string as column name, may contain a database name, schema name or asterisk (`*`) separated by a dot; surrounding parentheses are not added to `%column[]` modifier;                                                          |
| `%ex`                  | expands array as processor arguments                                                                                                                                                                                                  |
| `%raw`                 | inserts string argument as is                                                                                                                                                                                                         |
| `%%`                   | escapes to single `%` (useful in `date_format()`, etc.)                                                                                                                                                                               |
| `[[`, `]]`             | escapes to single `[` or `]` (useful when working with array, etc.)                                                                                                                                                                   |

Let's examine `%and` and `%or` behavior. If array key is numeric and its value is an array, value is expanded with `%ex` modifier. (See below.)

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
```

If you want select multiple rows with combined condition for each row, you may use multi-column `IN` expression. However, some databases do not support this feature, therefore Dbal provides universal `%multiOr` modifier that will handle this for you and will use alternative expanded verbose syntax. MultiOr modifier supports optional modifier appended to the column name, set it for all entries. Let's see an example:

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

You may add support for own modifier. To do that, create new factory class for SqlProcessor and use `setCustomModifier()` method:

```php
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\SqlProcessor;

class SqlProcessorFactory implements ISqlProcessorFactory
{
	public function create(IDriver $driver, array $config): SqlProcessor
	{
		$processor = new SqlProcessor($driver);
		$processor->setCustomModifier(
		    'mybool',
		    function (SqlProcessor $processor, $value) {
			    return $processor->processModifier('s', $bool ? 'yes' : 'no');
		    }
		);
		return $processor;
	}
}
```

### Modifier Resolver

SqlProcessor allows setting custom modifier resolver for any values passed for both implicit and explicit `%any` modifier. This way you may introduce custom processing for your custom types. For safety reasons it is possible to override only the `%any` modifier. To do so, implement `ISqlProcessorModifierResolver` interface and return the modifier name for the passed value. Finally, register the custom modifier resolver into SqlProcessor. This API is especially powerful in combination with custom modifiers.

```php
use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\ISqlProcessorModifierResolver;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\SqlProcessor;

class BrickSqlProcessorModifierResolver implements ISqlProcessorModifierResolver
{
    public function resolve($value): ?string
    {
        if ($value instanceof \Brick\DayOfWeek) {
            return 'brickDoW';
        }
        return null;
    }
}

class SqlProcessorFactory implements ISqlProcessorFactory
{
	public function create(IDriver $driver, array $config): SqlProcessor
	{
		$processor = new SqlProcessor($driver);
		$processor->setCustomModifier(
		    'brickDoW',
		    function (SqlProcessor $processor, $value) {
		        assert($value instanceof \Brick\DayOfWeek);
			    return $processor->processModifier('s', $value->getValue());
		    }
		);
		$processor->addModifierResolver(new BrickSqlProcessorModifierResolver());
		return $processor;
	}
}
```
