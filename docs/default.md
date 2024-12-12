## Nextras Dbal

Dbal provides concise and secure API to construct queries and fetch data from storage independently on the database engine.

Supported platforms:

- **MySQL** via `mysqli` or `pdo_mysql` extension,
- **Postgres** via `pgsql` or `pdo_pgsql` extension,
- **MS SQL Server** via `sqlsrv` or `pdo_sqlsrv` extension.

### Connection

The Connection instance is the main access point to the database. Connection's constructor accepts a configuration array. The possible keys depend on the specific driver; some configuration keys are shared for all drivers. To actual list of supported keys are enumerated in PhpDoc comment in driver's source code.

| Key                               | Description                                                                                                                                   |
|-----------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| `driver`                          | driver name, use `mysqli`, `pgsql`, `sqlsrv`, `pdo_mysql`, `pdo_pgsql`, `pdo_sqlsrv`                                                          |
| `host`                            | database server name     															    |             
| `port`                            | database server port     															    |
| `username`                        | username for authentication                                                                                                                   |
| `password`                        | password for authentication                                                                                                                   |
| `database`                        | database name                                                                                                                                 |
| `charset`                         | charset encoding of the connection                                                                                                            |
| `nestedTransactionsWithSavepoint` | boolean which indicates whether use save-points for nested transactions; `true` by default                                                    |
| `sqlProcessorFactory`             | factory implementing ISqlProcessorFactory interface; use for adding custom modifiers; `null` by default;                                      |
| `connectionTz`                    | timezone for the connection; pass a timezone name, `auto` or `auto-offset` keyword, see [DateTime TimeZones](datetime) chapter for more info; |
| `searchPath`                      | *PgSQL only*; sets the connection `search_path`;                                                                                              |
| `sqlMode`                         | *MySQL only*; sets the `sql_mode`, `TRADITIONAL` by default;                                                                                  |
| `ssl*`                            | *MySQL only*; use `sslKey`, `sslCert`, `sslCa`, `sslCapath` and `sslCipher` to set SSL options for connection;                                |

```php
$connection = new Nextras\Dbal\Connection([
	'driver'   => 'mysqli',
	'host'     => 'localhost',
	'port'	   => 3306,
	'username' => 'root',
	'password' => '****',
	'database' => 'test',
]);
```

The Connection's implementation is lazy; it connects to database only when needed. You can explicitly connect by calling `connect()` method; you can also `disconnect()` or `reconnect()` the connection. Use `ping()` method to avoid connection timeouts.

In real-world applications, you are expected to use a Dependency Injection Container. Dbal comes with integration for [Nette framework](config-nette) and [Symfony framework](config-symfony). Utilizing those extensions helps you to set up the Connection.

### Querying

Use the `query()` method to run SQL queries. The query method accepts a single SQL statement. Dbal supports parameter placeholders called modifiers - values are passed separately and their value will replace the placeholder with a properly escaped and sanitized value. Read more in the [Parameter Modifiers| param-modifiers] chapter.

```php
$connection->query('SELECT * FROM foo WHERE id = %i', 1);
// SELECT * FROM foo WHERE id = 1

$connection->query('SELECT * FROM foo WHERE title = %s', 'foo" OR 1=1');
// SELECT * FROM foo WHERE title = "foo\" OR 1=1"
```

Our SQL processor supports `[]` (square brackets) for easily escaping of column/table names. However, if you want to pass an input retrieved from a user as a column name, use the save `%column` modifier.

```php
$connection->query('SELECT * FROM [foo] WHERE %column = %i', 'id', 1);
// SELECT * FROM `foo` WHERE `id` = 1
```

To retrieve the last inserted id, use `getLastInsertedId()` method, it accepts a sequence name for PostgreSQL. The number of affected rows is available through `getAffectedRows()` method.

Each `query()` returns a new `Nextras\Dbal\Result\Result` instance. The result's instance allows iterating over the fetched rows and fetches each of them into a `Nextras\Dbal\Result\Row` instance. The `Row` instance is a simple value object with property access:

```php
$users = $connection->query('SELECT * FROM [users]');
foreach ($users as $row) {
	$row->name;
}
```

The `Result` object implements `SeekableIterator`, so you can iterate over the result. Also, you can use `fetch()` method to fetch a row, `fetchField()` to fetch the first field from the first row, or `fetchAll()` to return an array of rows' objects.

```php
$maximum = $connection->query('SELECT MAX([age]) FROM [users]')->fetchField();
```

### Transactions & savepoints

The Connection interface provides a convenient API for working with transactions. You can easily `beginTransaction()`, `commitTransaction()` and `rollbackTransaction()`. Usually, you need to react to an exception by calling the rollback method. For such a use case, there is a `transactional()` helper method that makes its callback atomic.

```php
$connection->transactional(function (Connection $connection) {
	$connection->query('INSERT INTO users %values', [
		'name' => 'new user'
	]);
	$connection->query('INSERT INTO urls %values', [
		'url' => 'new-user',
		'user_id' => $connection->getLastInsertedId();
	]);
});
```

If you call `beginTransaction()` repeatedly (without committing or rollbacking), connection will use savepoints for nested transaction simulation. It is possible to disable such behavior by setting `nestedTransactionsWithSavepoint` configuration option to `false`.

You may create, release, and roll back savepoints directly through appropriate methods.

```php
$connection->createSavepoint('beforeUpdate');
$isOk = ...;
if ($isOk) {
	$connection->releaseSavepoint('beforeUpdate');
} else {
	$connection->rollbackSavepoint('beforeUpdate');
}
```

Connection also supports setting a transaction isolation level. The default isolation level depends on the default setting of your database.

```php
$connection->setTransactionIsolationLevel(IConnection::TRANSACTION_SERIALIZABLE);
// other available constants:
// IConnection::TRANSACTION_READ_UNCOMMITTED
// IConnection::TRANSACTION_READ_COMMITTED
// IConnection::TRANSACTION_REPEATABLE_READ
```
