## Migration Guide for 6.0

This guide lists the BC breaks you are likely to encounter when upgrading from 5.x.

### BC Breaks

- **QueryBuilder join methods renamed:** `joinInner()` / `joinLeft()` / `joinRight()` → `addInnerJoin()` / `addLeftJoin()` / `addRightJoin()`. Their signatures are unchanged; the old names were removed.

    ```php
    // before (5.x)
    $builder->joinLeft('[books] AS [b]', '[a.id] = [b.authorId]');

    // after (6.0)
    $builder->addLeftJoin('[books] AS [b]', '[a.id] = [b.authorId]');
    ```

    Each `add*Join()` call appends another JOIN clause. To add a join at most once (deduplicated), use the new `joinOnce()` method. See the [Query Builder](query-builder) documentation for details.

- **`Fqn` constructor argument order changed** from `(string $name, string $schema)` to `(string $schema, string $name)`. Update any positional `new Fqn(...)` calls; reading the `$name` / `$schema` properties and using named arguments are unaffected.

    ```php
    // before (5.x)
    new Fqn('my_table', 'my_schema');

    // after (6.0)
    new Fqn('my_schema', 'my_table');
    ```

- **`IConnection::getLastInsertedId()` parameter widened** from `?string` to `string|Fqn|null`. Source-compatible for callers; implementers and overriders must widen their own signature.

- **`%and` / `%or` with a single auto-expanded group no longer wraps it in redundant parentheses.** The generated SQL is semantically identical but its text differs — update any exact SQL-string assertions.

### `#[\NoDiscard]` warnings (PHP 8.5+)

Result-oriented getters are now annotated with `#[\NoDiscard]`, so ignoring their return value triggers a warning on PHP 8.5+ (the attribute is inert on older versions). Affected methods include `Connection::getPlatform()` / `getDriver()` / `createQueryBuilder()` / `getAffectedRows()` / `getLastInsertedId()`, all `IPlatform` getters, `Result::fetchAll()` / `fetchField()` / `fetchPairs()` / `getColumns()`, `Row::toArray()`, and `Fqn::getUnescaped()`. `query()`, `fetch()` and `ping()` are intentionally not annotated.

### Other behavioral changes

- **PDO transaction commands now throw on a `false` result**, including deferred constraint failures at commit in silent error mode. Native PDO exceptions from transaction commands are converted through the platform-specific exception mapping and retained as the previous exception.
- **Concurrency errors have dedicated exception types**, all extending `QueryException` and implementing `Nextras\Dbal\Drivers\Exception\RetryableException`: `DeadlockException` (MySQL 1213, PostgreSQL `40P01`, SQL Server 1205), `SerializationFailureException` (PostgreSQL `40001`, SQL Server 3960), and `LockWaitTimeoutException` (MySQL 1205). These mappings apply to both native and PDO drivers, including commit failures. The marker allows applications to choose to retry the entire transaction after cleanup; DBAL does not retry automatically or reset ORM state. A connection failure or an uncertain commit outcome is not marked retryable.

- **`Connection::transactional()` rolls back on any `Throwable`**, including `Error` and `TypeError`. It rejects callbacks that leave the transaction nesting level changed, rolls back all levels opened within its scope on failure, and rejects nested use when `nestedTransactionsWithSavepoint` is disabled. Callbacks must not commit or roll back the transaction owned by `transactional()`.
- **Failed rollback in `transactional()` throws `Nextras\Dbal\Exception\TransactionRollbackException`**. Its `getPrevious()` returns the original operation or commit failure; `getRollbackException()` returns the rollback failure. DBAL attempts to disconnect the connection. If disconnection also fails, `getDisconnectException()` returns that failure and the connection must be discarded.

- **Postgres SQLSTATE `23001` is now classified as `ForeignKeyConstraintViolationException`** (Postgres 18 reports restrict violations with this code).
- **MySQL error 1298 now throws `UnknownMysqlTimezoneException`** with an extended message.
- Both new exception types subclass `QueryException`, so existing `catch (QueryException)` blocks keep working; only code matching the exact class or message text is affected.
- **`Result::fetchPairs()`** no longer stops early when a row evaluates as falsy (e.g. a `0` or `''` key/value); iteration now ends only on a real end of the result set.
- **SQL Server (`sqlsrv`, `pdo_sqlsrv`) now returns a zero-scale `numeric`/`decimal` as `int`.** A `numeric`/`decimal` with no fractional part (e.g. `numeric(18, 0)`) is normalized to `int`; columns with a non-zero scale are still returned as `string` to avoid precision loss. As a consequence, `Connection::getLastInsertedId()` on SQL Server now returns an `int` (matching MySQL and PostgreSQL) instead of a numeric `string`, because it is backed by `SELECT SCOPE_IDENTITY()`.

### For Custom Driver & Platform Authors

- **PDO driver `createException()` overrides need an optional fifth parameter**, `\Throwable|null $previousException = null`. Forward it to the exception constructor to preserve native PDO transaction failures.

- **`IPlatform` gained `formatLocalDate(DateTimeInterface $value): string`** — custom platforms must implement it.
- **`IDriver` identifier / savepoint methods widened to accept `string|Fqn`** (`getLastInsertedId()`, `createSavepoint()`, `releaseSavepoint()`, `rollbackSavepoint()`). Custom drivers must widen their signatures.

### Additive (no action required)

- New **PDO SQLite driver** and `SqlitePlatform`.
- New `%ld` (local date) modifier backed by `IPlatform::formatLocalDate()`.
- `QueryBuilder::distinct()` / `getDistinct()` for `SELECT DISTINCT` queries.
- `%and` / `%or` / `%multiOr` accept `Fqn`-based condition tuples, and `%column` accepts an `Fqn`.
