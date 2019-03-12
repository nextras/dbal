<?php

use Nextras\Dbal\Connection;

return function (Connection $connection, $config) {
	$dbname = $config['database'];
	$connection->query('SET FOREIGN_KEY_CHECKS = 0;');
	$connection->query('DROP DATABASE IF EXISTS %table', $dbname);
	$connection->query('DROP DATABASE IF EXISTS %table', "{$dbname}2");
	$connection->query('CREATE DATABASE IF NOT EXISTS %table', $dbname);
	$connection->query('CREATE DATABASE IF NOT EXISTS %table', "{$dbname}2");
	$connection->query('USE %table', $dbname);
	$connection->query('SET FOREIGN_KEY_CHECKS = 1;');
};
