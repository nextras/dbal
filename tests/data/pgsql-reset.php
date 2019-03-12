<?php

use Nextras\Dbal\Connection;

return function (Connection $connection) {
	$connection->query('DROP SCHEMA IF EXISTS public CASCADE');
	$connection->query('DROP SCHEMA IF EXISTS second_schema CASCADE');
	$connection->query('CREATE SCHEMA public');
	$connection->query('CREATE SCHEMA second_schema');
};
