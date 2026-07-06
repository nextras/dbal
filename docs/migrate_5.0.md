## Migration Guide for 5.0

The 5.0 release raises the minimum PHP version and reworks the driver, platform and schema-reflection layers. This guide lists the BC breaks you are likely to encounter; there are also smaller internal breaks that most applications will not hit.

### Requirements

- **PHP 8.1+** is now required (was 7.1+), and the `ext-json` extension is now required.

### BC Breaks

- **Removed deprecated root-namespace exception aliases.** The compatibility aliases in the `Nextras\Dbal` root namespace were removed; use the `Nextras\Dbal\Drivers\Exception\` classes instead: `ConnectionException`, `ConstraintViolationException`, `DriverException`, `ForeignKeyConstraintViolationException`, `NotNullConstraintViolationException`, `QueryException`, `UniqueConstraintViolationException` (and `Nextras\Dbal\Exception\IOException`).

- **`numeric` / `decimal` columns are no longer cast to `float`.** To avoid precision loss, their values are now returned as `string` — cast them yourself where you need a float. Real floating-point columns (`float4`/`float8`/`double`) are still returned as `float`.

- **`%dts` modifier renamed to `%ldt`** (local date-time). `%dts` still works but is deprecated.

- **QueryBuilder joins** method BC breaks are also present in the next Dbal 6.0 version. If possible, migrate to v6 directly.

  - **QueryBuilder: removed `innerJoin()`, `leftJoin()` and `rightJoin()`.** Use `joinInner()`, `joinLeft()`, `joinRight()` and fold the alias into the target expression:

      ```php
      // before
      $builder->leftJoin('books', 'b', '[a.id] = [b.authorId]');

      // after
      $builder->joinLeft('books AS [b]', '[a.id] = [b.authorId]');
      ```

  - **QueryBuilder: joins are no longer deduplicated by target expression.** Each `joinInner()` / `joinLeft()` / `joinRight()` call now appends a separate JOIN clause. In 4.x, repeating the same target expression silently replaced the previous join. (6.0 re-adds opt-in deduplication via `joinOnce()`.)

- **QueryBuilder constructor now takes an `IPlatform`** instead of an `IDriver`. This affects you only if you instantiate or extend `QueryBuilder` manually; the recommended `Connection::createQueryBuilder()` flow is unchanged.

    ```php
    // before (4.x)
    $builder = new QueryBuilder($connection->getDriver());

    // after (5.0)
    $builder = new QueryBuilder($connection->getPlatform());
    ```

- **`IConnection::query()` now has an explicit `string $expression` first parameter:** `query(string $expression, mixed ...$args)`. Passing the SQL string still works as before; calling it with no arguments, or implementing `IConnection`, requires the updated signature.

- **`IConnection::queryArgs()` first argument is now typed `string|array`** (was untyped).

- **`IConnection::transactional()` now declares a `: mixed` return type** — relevant for implementers/overriders.

- **Custom modifiers receive the `SqlProcessor` as their first argument.** A modifier registered via `SqlProcessor::setCustomModifier()` is now invoked as `$callback($processor, $value, $type)` (was `$callback($value, $type)`). Update your callback signature.

- **`SqlProcessor` constructor dropped the `IDriver` argument** — it is now `__construct(IPlatform $platform)`.

### Schema Reflection API

The schema-reflection result objects became immutable, read-only value objects.

- **`Column`, `Table` and `ForeignKey` are now built via their constructor and all properties are `readonly`.** You can no longer create them with `new Column()` and assign properties one by one. Reading properties after reflection still works.

- **`Table` and `ForeignKey` expose a `Fqn` value object** instead of separate `name`/`schema` string properties:
    - `Table`: `$table->name` / `$table->schema` → `$table->fqnName->name` / `$table->fqnName->schema`; `getNameFqn()` removed (use `$table->fqnName->getUnescaped()`).
    - `ForeignKey`: `name` / `schema` → `fqnName`; `refTable` is now an `Fqn` (was a string), `refTableSchema` removed; `getNameFqn()` / `getRefTableFqn()` removed.

- **`IPlatform::getColumns()`, `getForeignKeys()` and `getPrimarySequenceName()` gained a `?string $schema` parameter.** The old `"schema.table"` dotted-string convention is no longer parsed — pass the schema separately, e.g. `getColumns('my_table', 'my_schema')`.

### File Import

- **`Nextras\Dbal\Utils\FileImporter` was removed.** Use `IPlatform::createMultiQueryParser()` (requires the optional `nextras/multi-query-parser` package) to split a multi-query SQL file into individual queries, then execute them yourself.

### For Custom Driver, Platform & Result-Adapter Authors

These are niche but hard (fatal) breaks if you maintain a custom driver, platform or result adapter.

- **`IResultAdapter` moved** from `Nextras\Dbal\Drivers\IResultAdapter` to `Nextras\Dbal\Result\IResultAdapter`. Its `TYPE_*` constants were removed, `getTypes()` now returns the native driver type per column (no type bitmask), and it gained required methods `toBuffered()`, `toUnbuffered()` and `getNormalizers()`.

- **`IDriver` was slimmed down.** All value-conversion methods (`convertToPhp()`, `convert*ToSql()`) and `modifyLimitQuery()` were removed — formatting moved to `IPlatform` (`format*()` / `formatLimitOffset()`). The `TYPE_*` constants were removed and a new `getConnectionTimeZone()` method was added.

- **`IPlatform` gained many methods** that custom platforms must implement: `formatString()`, `formatStringLike()`, `formatJson()`, `formatBool()`, `formatIdentifier()`, `formatDateTime()`, `formatLocalDateTime()`, `formatDateInterval()`, `formatBlob()`, `formatLimitOffset()` and `createMultiQueryParser()`.

- **`Result` constructor dropped its `IDriver` argument** — it is now `__construct(IResultAdapter $adapter)`; normalizers are taken from the adapter.

- **Built-in result adapters now require a normalizer factory** constructor argument.

- **`ISqlProcessorFactory::create()` parameter type changed** from `Connection` to `IConnection`.

- **`DriverException` constructor:** `$errorSqlState` is now non-nullable with a default of `''` (was `null`).

### Additive (no action required)

- New PDO drivers: `pdo_mysql`, `pdo_pgsql`, `pdo_sqlsrv`.
- Read-only schema-reflection API and the `Fqn` value object.
- Result buffering: `Result::buffered()` / `unbuffered()`, and `Result::getColumns()`.
- `%table` / `%column` now accept an `array{schema, table}` and `Fqn` instances; new array-spread modifier; `literal-string` type annotations for safer SQL construction.
