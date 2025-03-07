## Named Timezone Support in MySQL

MySQL, *by default*, does not "support" named timezones, therefore you could get errors similar to `Unknown or incorrect time zone: 'Europe/Prague'`. In fact, MySQL just does not know the correct timeshift for this name and allows you to import the configuration.

Solutions:

1. Import named timezones (see bellow, recommended).
2. Use setting `connectionTz` with value `auto-offset`, which will use "offset" time zone setting. This will produce correct datetime in PHP, however, your SQL functions may start returning incorrect results, because MySQL will not be able to calculate proper difference between two date time since in "offset" mode there are no day-light saving shifts.

### Importing Named Timezones

#### Linux

Run this command, where `root` is the username which has access to `mysql` database.

```
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql
```

#### Windows

For MySQL **5.7+** download zipped SQL inserts from http://dev.mysql.com/downloads/timezones.html and run them in context of `mysql` database.

For MySQL **up to 5.6** download archive from http://dev.mysql.com/downloads/timezones.html. Unzip the archive and copy the files to your `mysql` database in data dir (eg. `C:\<your mysql dir>\data\mysql`).


#### MariaDB

The principles are almost the same, follow the official documentation at https://mariadb.com/kb/en/time-zones/#mysql-time-zone-tables.
