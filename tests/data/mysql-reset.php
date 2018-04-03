<?php

use Nextras\Dbal\Connection;

return function (Connection $connection, $dbname) {
	$connection->query('DROP DATABASE IF EXISTS %table', "{$dbname}2");
	$connection->query('DROP DATABASE IF EXISTS %table', $dbname);
	$connection->query('CREATE DATABASE IF NOT EXISTS %table', $dbname);
	$connection->query('CREATE DATABASE IF NOT EXISTS %table', "{$dbname}2");
	$connection->query('USE %table', $dbname);
};
