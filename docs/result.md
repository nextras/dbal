## Result

`Connection::query()` method returns a `Nextras\Dbal\Result\Result` instance. You can call fetching methods to get the fetched data.

- `Result::fetchAll()` returns an array of `Nextras\Dbal\Result\Row` instances.
- `Result::fetch()` returns the next unfetched `Row` instance.
- `Result::fetchField($column)` returns nth column of the next unfetched `Row` instance.
- `Result::fetchPairs($key, $value)` transforms all fetched `Row` instances into an associative array, see examples below.

```php
$result = $connection->query('SELECT ...');
foreach ($result as $row) { // equals to $result->fetchAll() as $row
}


$result = $connection->query('SELECT ...');
$row = $result->fetch();


$result = $connection->query('SELECT name FROM ...');
$name = $result->fetchField();


$result = $connection->query('SELECT name, age FROM ...');
$assoc = $result->fetchPairs('name', 'age');
// ['peter' => 20, 'john' => 13]

$assoc = $result->fetchPairs(null, 'age');
// [20, 13]

$assoc = $result->fetchPairs('name', null);
// [
//  'peter' => new Row(['name' => 'peter', 'age' => 20]),
//  'john' => new Row(['name' => 'john', 'age' => 13]),
// ]
```

### Row

Row instances hold the data of specific fetched result-row. You can access data by property access with a column name. Use `getNthField()` method to retrieve a column by its numeric index.

```php
$row = $connection->query('SELECT name, age FROM ...')->fetch();

echo $row->name;
echo $row->age;

echo $row->getNthField(0); // prints name
```


### Buffering

Some database drivers do not support rewinding or seeking the result. I.e. you cannot iterate over the result multiple times. Similarly, you cannot use `seek()` method to skip some rows. Dbal's emulated buffering comes to solve this for you. The relevant drivers automatically enable emulated buffering. You can disable or enable it for particular `Result` instances.

```php
$result = $connection->query('...')->buffered(); // enable emulated buffering
$result->unbuffered(); // disable the emulated buffering
```

If the unbuffered Result was already partially consumed, enabling buffering does nothing and Result will potentially throw an exception when rewinded or seeked. If the buffered Result was already partially consumed, disabling buffering does nothing and Result will still use the buffer.
