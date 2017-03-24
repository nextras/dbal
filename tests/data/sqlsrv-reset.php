<?php

use Nextras\Dbal\Connection;

return function (Connection $connection, $dbname) {
	$config = $connection->getConfig();
	$config['database'] = $config['dbname'] = 'master';
	$connection->reconnectWithConfig($config);

	$connection->query('DROP DATABASE IF EXISTS %table', $dbname);
	$connection->query('CREATE DATABASE %table', $dbname);

	$config['database'] = $config['dbname'] = $dbname;
	$connection->reconnectWithConfig($config);
};
