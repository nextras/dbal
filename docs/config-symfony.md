## Configuring Symfony Bundle

Nextras Dbal comes with Symfony Bundle that allows easy setup into your Symfony application.

Start with enabling Dbal's bundle:

```php
return [
    // ...
    Nextras\Dbal\Bridges\SymfonyBundle\NextrasDbalBundle::class => ['all' => true],
];
```

Create a configuration named `nextras_dbal` and set up the `Connection`:

```yaml
nextras_dbal:
  driver: mysqli
  host: 127.0.0.1
  port: 3306
  database: your-project
  username: db-username
  password: db-password
```

If you need multiple connections, include connection configuration into `connections` key and define the default connection with `default_connection` key:

```yaml
nextras_dbal:
  default_connection: project1
  connections:
    -
      name: project1
      driver: mysqli
      database: your-project1
    -
      name: project2
      driver: mysqli
      database: your-project2
```

**Configuration keys** are those accepted by `Connection` instance, the actual driver respectively. See [Connection](default) chapter.

The bundle takes additional configurations:

- `maxQueries` (default `100`): number of logged queries into QueryDataCollector.

The define custom `Nextras\Dbal\ISqlProcessorFactory` instance, define `nextras_dbal.default.sqlProcessorFactory` named service, where the `default` is the name of relevant connection.
