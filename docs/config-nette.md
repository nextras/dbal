## Configuring Nette DI Extension

Nextras Dbal comes with Nextras DI Extension that allows easy setup into your Nette application.

The minimal supported version of Nette/DI is 3.1.

Define the extension and provide its configuration:

```neon
extensions:
	nextras.dbal: Nextras\Dbal\Bridges\NetteDI\DbalExtension

nextras.dbal:
	driver: mysqli
	host: localhost
	port: 3306
	database: your-project
	username: db-username
	password: db-password
	connectionTz: Europe/Prague
	sqlProcessorFactory: @Custom\SqlProcessorFactory

services:
	- Custom\SqlProcessorFactory
```

If you need multiple connections, register the extension more than once with different names and choose which connection should be autowired. Autowiring is enabled by default, so disable it for additional connections.

```neon
extensions:
	nextras.dbal1: Nextras\Dbal\Bridges\NetteDI\DbalExtension
	nextras.dbal2: Nextras\Dbal\Bridges\NetteDI\DbalExtension

nextras.dbal1:
	driver: mysqli
	database: your-project1
nextras.dbal2:
	driver: mysqli
	database: your-project2
	autowired: false
```

Configuration keys are the same as those accepted by `Connection` and the selected driver. See the [Connection](default) chapter.

The extension takes additional configurations:

- `panelQueryExplain` (default `true` if Tracy is available): enables or disables query `EXPLAIN` output in the Tracy panel.
- `maxQueries` (default `100`): number of logged queries in the Tracy panel.
- `sqlProcessorFactory`: a reference to a `Nextras\Dbal\ISqlProcessorFactory` service.
