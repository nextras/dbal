## Result

`Connection::query()` returns a `Nextras\Dbal\Result\Result` instance. You can iterate over it or use fetching methods to retrieve data.

- `Result::fetchAll()` returns an array of `Nextras\Dbal\Result\Row` instances.
- `Result::fetch()` returns the next unfetched `Row` instance.
- `Result::fetchField($column)` returns nth column of the next unfetched `Row` instance.
- `Result::fetchPairs($key, $value)` transforms all fetched `Row` instances into an associative array, see examples below.

```php
$result = $connection->query('SELECT ...');
foreach ($result as $row) {
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

`foreach` iterates over the result directly. Unlike `fetchAll()`, it does not eagerly materialize all rows into an array first.

### Row

`Row` instances hold data for a single fetched row. You can access columns via property access. Use `getNthField()` to retrieve a column by its numeric index.

```php
$row = $connection->query('SELECT name, age FROM ...')->fetch();

echo $row->name;
echo $row->age;

echo $row->getNthField(0); // prints name
```


### Buffering

Some database drivers do not support rewinding or seeking the result. That means you cannot iterate over the result multiple times, and `seek()` may not be available. Dbal provides emulated buffering for those cases. You can enable or disable buffering for a particular `Result` instance.

```php
$result = $connection->query('...')->buffered(); // enable emulated buffering
$result->unbuffered(); // disable emulated buffering again
```

If an unbuffered `Result` was already partially consumed, enabling buffering does nothing and the result may still throw an exception when rewound or seeked. If a buffered `Result` was already partially consumed, disabling buffering does nothing and the existing buffer remains in use.

### Value Normalization

Dbal automatically normalizes selected column values to PHP types based on driver metadata. You can disable or re-enable that behavior per result:

```php
$result = $connection->query('SELECT * FROM events');
$result->setValueNormalization(false);
$result->setValueNormalization(true);
```

See the [Result Normalization](result-normalization) chapter for the exact behavior of each driver.
