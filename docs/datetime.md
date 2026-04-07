## DateTime

Database engines provide different types for storing date-times, and the type names are often misleading. This chapter covers the basics and Dbal's approach to datetime and timezone handling.

In practice, it helps to distinguish two kinds of values:

- **Local DateTime**: a date and time without an exact position on the timeline. We do not know which timezone the event belongs to, so the value is treated as local. Example: the start of a school year.
- **DateTime**: an exact timestamp on the timeline. Example: the start time of a meeting in a calendar. This type is also referred to as an `Instant`.
  - **UTC DateTime**: an exact timestamp represented in UTC.
  - **Zoned DateTime**: an exact timestamp plus timezone context, for example when displaying an event in the viewer's local timezone.

The following table presents a matrix of available DB date-time types:

|            | Local DateTime<br>no timezone handling | DateTime<br>timezone conversion | DateTime<br>timezone stored | 
|------------|----------------------------------------|---------------------------------|-----------------------------|
| MySQL      | `datetime`                             | `timestamp`                     | -                           |
| Postgres   | `timestamp`                            | `timestamptz`                   | -                           |
| SQL Server | `datetime`, `datetime2`                | -                               | `datetimeoffset`            |
| Sqlite     | -                                      | -                               | -                           |
- 
- **no timezone handling**: the database stores the timestamp as-is and does not modify it; this is the simplest approach, but the database cannot reason about timezone transitions such as daylight saving time.
- **timezone conversion**: the database stores the timestamp in UTC and reads it in the connection timezone.
- **timezone stored**: the database stores the timezone-aware timestamp directly and returns it without conversion.

Dbal offers a connection timezone configuration option, `connectionTz`, which defines the timezone used for communication with the database. By default, it uses PHP's current default timezone. Configure it with a timezone name such as `Europe/Prague`.

Dbal comes with two query modifiers:

| Type           | Modifier | Description                                                                                                                    |
|----------------|----------|--------------------------------------------------------------------------------------------------------------------------------|
| local datetime | `%ldt`   | passes a `DateTimeInterface` value as a local datetime without timezone conversion; formerly known as datetime simple (`%dts`) |
| datetime       | `%dt`    | converts a `DateTimeInterface` value to  the connection timezone                                                               |

---------------

### MySQL

##### Writing 

| Type           | Modifier | Comment                                                                                                                  |
|----------------|----------|--------------------------------------------------------------------------------------------------------------------------|
| local datetime | `%ldt`   | timezone (offset) is removed                                                                                             |
| datetime       | `%dt`    | value is converted to connection timezone and timezone offset is removed if properly stored to `timestamp` column type   |

##### Reading

| Type           | Column Type | Comment                                                                             |
|----------------|-------------|-------------------------------------------------------------------------------------|
| local datetime | `datetime`  | value is converted into application timezone                                        |
| datetime       | `timestamp` | value is interpreted in connection timezone and converted into application timezone |

##### Connection Time Zone

By default, MySQL does not support named timezones. See the [setup chapter](timezones-mysql-support) for configuration details. You can still pass only a timezone offset such as `+03:00`, but that is not ideal. Prefer the special `auto-offset` value, which is dynamically converted to the current PHP timezone offset.

This makes Dbal functional, but some SQL queries and expressions may still produce incorrect results, especially functions that calculate with dates directly in the database, such as `TIMEDIFF` or `ADDDATE`.

---------------

### Postgres

##### Writing

| Type           | Modifier | Comment                                                                                                                  |
|----------------|----------|--------------------------------------------------------------------------------------------------------------------------|
| local datetime | `%ldt`   | timezone (offset) is removed                                                                                             |
| datetime       | `%dt`    | value is converted to connection timezone and timezone offset is removed if properly stored to `timestamptz` column type |

##### Reading

| Type           | Column Type   | Comment                                      |
|----------------|---------------|----------------------------------------------|
| local datetime | `timestamp`   | value is converted into application timezone |
| datetime       | `timestamptz` | value is converted into application timezone |

---------------

### SQL Server


##### Writing

| Type           | Modifier | Comment                                                                                      |
|----------------|----------|----------------------------------------------------------------------------------------------|
| local datetime | `%ldt`   | timezone (offset) is removed                                                                 |
| datetime       | `%dt`    | no timezone conversion is done and the timezone offset is stored in `datetimeoffset` db type |

##### Reading

| Type           | Column Type      | Comment                                                                                                                  |
|----------------|------------------|--------------------------------------------------------------------------------------------------------------------------|
| local datetime | `datetime`       | value is converted into application timezone                                                                             |
| datetime       | `datetimeoffset` | value is read with timezone offset and no further modification is done - i.e. no application timezone conversion happens |

--------------------------

### Sqlite

Sqlite does not have dedicated date/time storage types. Dbal therefore relies on the declared column type and uses a convention for exact timestamps.

Use `datetime(your_column, 'unixepoch', 'localtime')` to convert stored timestamp to your local time-zone. Read more in the [official documentation](https://sqlite.org/lang_datefunc.html#modifiers).

##### Writing

| Type           | Modifier | Comment                                                                                         |
|----------------|----------|-------------------------------------------------------------------------------------------------|
| local datetime | `%ldt`   | the timezone offset is removed and value is formatted as ISO string without the timezone offset |
| datetime       | `%dt`    | value is converted to connection timezone and stored as unix timestamp in milliseconds          |

##### Reading

| Type           | Declared Column Type                                        | Comment                                                                     |
|----------------|-------------------------------------------------------------|-----------------------------------------------------------------------------|
| local datetime | `date`, `datetime`, `time`                                  | built-in aliases supported by the SQLite driver                             |
| local datetime | `localdate`, `localdatetime`, `localtime`                   | short aliases if you want to distinguish intent explicitly                  |
| local datetime | `dbal_local_date`, `dbal_local_datetime`, `dbal_local_time` | recommended explicit Dbal convention for Sqlite schemas                     |
| datetime       | `timestamp`, `unixtimestamp`, `dbal_timestamp`              | interpreted as unix timestamp in milliseconds and converted to app timezone |

##### Detection Notes

- Sqlite detection is based on the declared column type returned by PDO metadata.
- `dbal_timestamp` is the recommended type name for exact timestamps stored as unix milliseconds.
- `dbal_local_date`, `dbal_local_datetime`, and `dbal_local_time` are the recommended type names for local values stored as strings.
- `dbal_bool` is supported as an explicit boolean declared type.
- Other supported scalar type aliases are standard SQL-style names such as `int`, `integer`, `tinyint`, `smallint`, `bigint`, `real`, `float`, `numeric`, and `decimal`.
- If you use unrecognized custom type names, Dbal will not auto-normalize them.
