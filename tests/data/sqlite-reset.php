<?php declare(strict_types = 1);


use Nextras\Dbal\Connection;


return function (Connection $connection, array $config): void {
	$connection->disconnect();
	@unlink($config['filename']);
	$connection->connect();
};
