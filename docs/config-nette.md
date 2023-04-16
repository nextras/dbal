## Configuring Nette DI Extension

Nextras Dbal comes with Nextras DI Extension that allows easy setup into your Nette application.

Simply define the extension and provide a default configuration:

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

If you need multiple connections, install the extension once again with a different name and choose which connection
will be autowired (the default is to autowire, so disable it for the additional extension).

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

**Configuration keys** are those accepted by `Connection` instance, the actual driver respectively. See [Connection](default) chapter.

The extension takes additional configurations:

- `panelQueryExplain` (default `true` if Tracy is available): enables/disables panel for Trace.
- `maxQueries` (default `100`): number of logged queries in the Tracy panel.
- `sqlProcessorFactory` a reference to `Nextras\Dbal\ISqlProcessorFactory` service.
