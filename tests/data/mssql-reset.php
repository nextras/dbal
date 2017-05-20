<?php

use Nextras\Dbal\Connection;

return function (Connection $connection, $dbname) {
	$connection->reconnectWithConfig(['database' => 'master']);

	$connection->query('DROP DATABASE IF EXISTS %table', $dbname);
	$connection->query('CREATE DATABASE %table', $dbname);

	$connection->reconnectWithConfig(['database' => $dbname]);
};
