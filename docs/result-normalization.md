## Result Normalization

Dbal automatically normalizes selected result values to PHP types based on driver metadata.

Normalization is enabled by default for every `Nextras\Dbal\Result\Result` instance returned from `Connection::query()`.

```php
$result = $connection->query('SELECT * FROM events');

$result->setValueNormalization(false); // return raw driver values
$result->setValueNormalization(true);  // restore default driver normalization
```

Normalization is decided per column from metadata reported by the active driver. Dbal does not inspect SQL expressions semantically; it relies on the type information the extension exposes for each selected column. That metadata is not equally detailed across all drivers, and for some queries it may be incomplete or missing entirely.

If a type is not recognized, Dbal leaves the value unchanged and returns the original driver value. This raw value can differ not only between drivers, but sometimes even between different PHP versions of the same driver, because native extensions and PDO metadata handling can change over time.

### General Rules

- Integers are normalized to `int`.
- Floating-point values are normalized to `float` where the driver metadata clearly marks them as floating-point.
- Date and time values are normalized to `Nextras\Dbal\Utils\DateTimeImmutable`.

### MySQL

Drivers: `mysqli`, `pdo_mysql`.

Notes:
- Decimal values are left as strings.
- `timestamp` is treated as an exact timestamp.
- `datetime` and `date` are treated as local values.

| Column Type in DB                                  | PHP Type                               |
|----------------------------------------------------|----------------------------------------|
| `BIT`, `TINY`, `SHORT`, `LONG`, `LONGLONG`, `YEAR` | `int`                                  |
| `FLOAT`, `DOUBLE`                                  | `float`                                |
| `datetime`, `date`                                 | `Nextras\Dbal\Utils\DateTimeImmutable` |
| `timestamp`                                        | `Nextras\Dbal\Utils\DateTimeImmutable` |
| `time`                                             | `DateInterval`                         |

### PostgreSQL

Drivers: `pgsql`, `pdo_pgsql`.

Notes:
- `numeric` is left as string.
- PostgreSQL date/time values are parsed from textual representation and then converted to the application timezone.
- `pdo_pgsql` leaves `bool` values untouched because PDO already returns a suitable scalar value.

| Column Type in DB                                    | PHP Type                               |
|------------------------------------------------------|----------------------------------------|
| `int2`, `int4`, `int8`                               | `int`                                  |
| `float4`, `float8`                                   | `float`                                |
| `bool` (`pgsql`)                                     | `bool`                                 |
| `date`, `time`, `timestamp`, `timetz`, `timestamptz` | `Nextras\Dbal\Utils\DateTimeImmutable` |
| `interval`                                           | `DateInterval`                         |
| `bit`, `varbit`                                      | `int`                                  |
| `bytea`                                              | `string`                               |

### SQL Server

Drivers: `sqlsrv`, `pdo_sqlsrv`.

Notes:
- `datetimeoffset` keeps the stored offset semantics.
- Decimal and money-like values are left as strings.

| Column Type in DB                                        | PHP Type                               |
|----------------------------------------------------------|----------------------------------------|
| integer types                                            | `int`                                  |
| `real`                                                   | `float`                                |
| `bit`                                                    | `bool`                                 |
| `date`, `time`, `datetime`, `datetime2`, `smalldatetime` | `Nextras\Dbal\Utils\DateTimeImmutable` |
| `datetimeoffset`                                         | `Nextras\Dbal\Utils\DateTimeImmutable` |

### Sqlite

Driver: `pdo_sqlite`.

Notes:
- SQLite normalization depends on the declared column type name.
- Local datetime aliases are parsed as local values.
- Datetime aliases are interpreted as unix timestamps in milliseconds.
- `%dt` writes unix timestamps in milliseconds.
- `%ldt` writes local string values without timezone offset.
- If you use an unrecognized custom declared type, Dbal leaves the value unchanged.
- Generic SQLite `text` and `varchar` columns are not auto-normalized.

| Column Type in DB                                                                                                                  | PHP Type                               |
|------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------|
| `int`, `integer`, `tinyint`, `smallint`, `mediumint`, `bigint`, `unsigned big int`, `int2`, `int8`                                 | `int`                                  |
| `real`, `double`, `double precision`, `float`, `numeric`, `decimal`                                                                | `float`                                |
| `bool`, `boolean`, `bit`, `dbal_bool`                                                                                              | `bool`                                 |
| `date`, `datetime`, `time`, `localdate`, `localdatetime`, `localtime`, `dbal_local_date`, `dbal_local_datetime`, `dbal_local_time` | `Nextras\Dbal\Utils\DateTimeImmutable` |
| `timestamp`, `unixtimestamp`, `dbal_timestamp`                                                                                     | `Nextras\Dbal\Utils\DateTimeImmutable` |
