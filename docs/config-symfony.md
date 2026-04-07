## Configuring Symfony Bundle

Nextras Dbal comes with Symfony Bundle that allows easy setup into your Symfony application.

The minimal supported version of Symfony is 5.4.

Start with enabling Dbal's bundle:

```php
return [
    // ...
    Nextras\Dbal\Bridges\SymfonyBundle\NextrasDbalBundle::class => ['all' => true],
];
```

Create a configuration named `nextras_dbal` and set up the default connection:

```yaml
nextras_dbal:
  driver: mysqli
  host: 127.0.0.1
  port: 3306
  database: your-project
  username: db-username
  password: db-password
```

This shorthand is normalized internally to a `connections.default` entry.

If you need multiple connections, configure them under the `connections` key and select the default one with `default_connection`:

```yaml
nextras_dbal:
  default_connection: project1
  connections:
    project1:
      driver: mysqli
      host: 127.0.0.1
      database: your-project1
    project2:
      driver: mysqli
      host: 127.0.0.1
      database: your-project2
```

Configuration keys for each connection are the same as those accepted by `Connection` and the selected driver. See the [Connection](default) chapter.

The bundle takes additional configurations:

- `max_queries` (default `100`): number of queries kept in the Symfony profiler.
- `profiler` (per connection, default `kernel.debug`): enables or disables the profiler collector for that connection.
- `profilerExplain` (per connection, default `true`): enables or disables `EXPLAIN` output in the profiler collector.

To define a custom `Nextras\Dbal\ISqlProcessorFactory` for a connection, register a service named `nextras_dbal.<connection-name>.sqlProcessorFactory`. For the default connection, that service name is `nextras_dbal.default.sqlProcessorFactory`.
