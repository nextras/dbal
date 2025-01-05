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

- **Postgres SQLSTATE `23001` is now classified as `ForeignKeyConstraintViolationException`** (Postgres 18 reports restrict violations with this code).
- **MySQL error 1298 now throws `UnknownMysqlTimezoneException`** with an extended message.
- Both new exception types subclass `QueryException`, so existing `catch (QueryException)` blocks keep working; only code matching the exact class or message text is affected.
- **`Result::fetchPairs()`** no longer stops early when a row evaluates as falsy (e.g. a `0` or `''` key/value); iteration now ends only on a real end of the result set.
- **SQL Server (`sqlsrv`, `pdo_sqlsrv`) now returns a zero-scale `numeric`/`decimal` as `int`.** A `numeric`/`decimal` with no fractional part (e.g. `numeric(18, 0)`) is normalized to `int`; columns with a non-zero scale are still returned as `string` to avoid precision loss. As a consequence, `Connection::getLastInsertedId()` on SQL Server now returns an `int` (matching MySQL and PostgreSQL) instead of a numeric `string`, because it is backed by `SELECT SCOPE_IDENTITY()`.

### For Custom Driver & Platform Authors

- **`IPlatform` gained `formatLocalDate(DateTimeInterface $value): string`** — custom platforms must implement it.
- **`IDriver` identifier / savepoint methods widened to accept `string|Fqn`** (`getLastInsertedId()`, `createSavepoint()`, `releaseSavepoint()`, `rollbackSavepoint()`). Custom drivers must widen their signatures.

### Additive (no action required)

- New **PDO SQLite driver** and `SqlitePlatform`.
- New `%ld` (local date) modifier backed by `IPlatform::formatLocalDate()`.
- `QueryBuilder::distinct()` / `getDistinct()` for `SELECT DISTINCT` queries.
- `%and` / `%or` / `%multiOr` accept `Fqn`-based condition tuples, and `%column` accepts an `Fqn`.
