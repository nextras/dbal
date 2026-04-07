## Nextras Dbal

Dbal provides a concise and secure API for constructing queries and fetching data independently of the database engine.

Supported platforms:

- **MySQL** via `mysqli` or `pdo_mysql` extension,
- **Postgres** via `pgsql` or `pdo_pgsql` extension,
- **MS SQL Server** via `sqlsrv` or `pdo_sqlsrv` extension,
- **Sqlite** via `pdo_sqlite` extension.

### Connection

The `Connection` instance is the main access point to the database. Its constructor accepts a configuration array. The supported keys depend on the selected driver, although some keys are shared across all drivers. The authoritative list is documented in the driver source code and related bridge configuration.

| Key                               | Description                                                                                                                                    |
|-----------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------|
| `driver`                          | driver name; use `mysqli`, `pgsql`, `sqlsrv`, `pdo_mysql`, `pdo_pgsql`, or `pdo_sqlsrv`                                      , or `pdo_sqlite` |
| `host`                            | database server name                                                                                                                           |
| `port`                            | database server port                                                                                                                           |
| `username`                        | username for authentication                                                                                                                    |
| `password`                        | password for authentication                                                                                                                    |
| `database`                        | database name                                                                                                                                  |
| `charset`                         | charset encoding of the connection                                                                                                             |
| `nestedTransactionsWithSavepoint` | whether to use savepoints for nested transactions; `true` by default                                                                           |
| `sqlProcessorFactory`             | factory implementing `ISqlProcessorFactory`; use  it for adding custom modifiers; `null` by default                                            |
| `connectionTz`                    | connection timezone; pass a timezone name, `auto`, or `auto-offset`; see the [DateTime](datetime) chapter for details                          |
| `searchPath`                      | *PostgreSQL only*; sets the connection `search_path`                                                                                           |
| `sqlMode`                         | *MySQL only*; sets the `sql_mode`; `TRADITIONAL` by default                                                                                    |
| `ssl*`                            | *MySQL only*; use `sslKey`, `sslCert`, `sslCa`, `sslCapath`, and `sslCipher` to configure SSL                                                  |

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

`Connection` is lazy; it connects to the database only when needed. You can explicitly connect by calling `connect()`, and you can also `disconnect()` or `reconnect()` the connection. `ping()` lets you check whether the connection is still alive.

In real-world applications, you will usually use a dependency injection container. Dbal includes integrations for the [Nette framework](config-nette) and the [Symfony framework](config-symfony), which simplify connection setup.

### Querying

Use `query()` to run SQL queries. The method accepts a single SQL statement. Dbal supports parameter placeholders called modifiers: values are passed separately and safely substituted into the statement. See the [Modifiers](param-modifiers) chapter for details.

```php
$connection->query('SELECT * FROM foo WHERE id = %i', 1);
// SELECT * FROM foo WHERE id = 1

$connection->query('SELECT * FROM foo WHERE title = %s', 'foo" OR 1=1');
// SELECT * FROM foo WHERE title = "foo\" OR 1=1"
```

The SQL processor supports `[]` for escaping column and table names inline. If you need to pass a column name dynamically, use the safe `%column` modifier instead.

```php
$connection->query('SELECT * FROM [foo] WHERE %column = %i', 'id', 1);
// SELECT * FROM `foo` WHERE `id` = 1
```

To retrieve the last inserted id, use `getLastInsertedId()` method, it accepts a sequence name for PostgreSQL. The number of affected rows is available through `getAffectedRows()` method.

Each `query()` call returns a new `Nextras\Dbal\Result\Result` instance. Iterating over it yields `Nextras\Dbal\Result\Row` objects. `Row` is a simple value object with property access:

```php
$users = $connection->query('SELECT * FROM [users]');
foreach ($users as $row) {
	$row->name;
}
```

`Result` implements `SeekableIterator`, so you can iterate over it directly. You can also use `fetch()` to fetch the next row, `fetchField()` to fetch the first field from the next row, or `fetchAll()` to materialize all remaining rows into an array.

```php
$maximum = $connection->query('SELECT MAX([age]) FROM [users]')->fetchField();
```

### Transactions & savepoints

The `Connection` interface provides a convenient API for working with transactions. You can call `beginTransaction()`, `commitTransaction()`, and `rollbackTransaction()` directly. For the common case of rolling back on exceptions, use `transactional()`, which executes the callback atomically.

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

If you call `beginTransaction()` repeatedly without committing or rolling back, the connection uses savepoints to simulate nested transactions. You can disable this behavior by setting `nestedTransactionsWithSavepoint` to `false`.

You can also create, release, and roll back savepoints directly:

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
