## DateTime

Database engines provide different types for storing date-times. Also, the type naming is often misleading. This documentation chapter covers the basics and Dbal's solution to the datetime & timezone handling.

Generally, we recognize two types of date-time types:

- **Local DateTime** - it is a date time which has not an exact position on the *time-line*; simply, we do not know in which time zone the event happened, therefore we consider the information as a local; example: date time when the school year begins, since the country may be across more the timezones, this type of information may be stored as a local date time, i.e., striping the "exactness" may be an advantage here;
- **DateTime** - is an exact timestamp on the *time-line*; example: timestamp of the meeting start in a calendar; this type is also referred as an `Instant` type.
   - **UTC DateTime** - having this type represented in UTC means we don't know an exact context where it happened; it could be in the day or in the night;
   - **Zoned DateTime** - is an exact timestamp on the *time-line* plus an additional context of specific time-zone; either we use reader's timezone or timezone of the location where the timestamp "happened"; example: presenting an online streaming event start - since it is pretty usual that this event will be watched from multiple places, we need add reader's timezone context to the *stored* UTC DateTime.

The following table presents a matrix of available DB date-time types:

|             | Local DateTime<br>no timezone handling  | DateTime<br>timezone conversion | DateTime<br>timezone stored | 
|-------------|-----------------------------------------|---------------------------------|-----------------------------|
| MySQL       | `datetime`                              | `timestamp`                     | -                           |
| Postgres    | `timestamp`                             | `timestamptz`                   | -                           |
| SQL Server  | `datetime`, `datetime2`                 | -                               | `datetimeoffset`            |

- **no timezone handling**: database stores the time-stamp and does not do any modification to it; this is the easiest solution, but brings a disadvantage: database cannot exactly diff two time-stamps, i.e. it may produce wrong results because day-light saving shift is needed but db does not know which zone to use for the calculation;
- **timezone conversion**: database stores the time-stamp unified in UTC and reads it in connection's timezone;
- **timezone stored**: database does not do any conversion, it just stores the timezoned timestamp and returns it back;

Dbal offers a **connection time zone** configuration option (`connectionTz`) that defines the timezone for database connection communication; it equals to PHP's current default timezone by default. This option is configured by a timezone name, e.g. `Europe/Prague` string.

Dbal comes with two query modifiers:

| Type           | Modifier | Description                                                                                                                                |
|----------------|----------|--------------------------------------------------------------------------------------------------------------------------------------------|
| local datetime | `%ldt`   | passes DateTime(Interface) object as it is, without any timezone conversion and identification; formerly known as datetime simple (`%dts`) |
| datetime       | `%dt`    | converts DateTime(Interface) object to connection timezone;                                                                                |

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

By default, MySQL server does not support named timezones, see [the setup chapter](timezones-mysql-support) how to configure them. Still, there is a possibility to pass only a timezone offset configuraion, e.g. `+03:00`, but this is not ideal. Use rather magic `auto-offset` value that will be dynamically converted to the current PHP's timezone offset.

This will make Dbal fully functional, although some SQL queries and expressions may not return correctly calculated results, e.g. functions calculating two-date operations directly in the database - `TIMEDIFF`, `ADDDATE`.

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
