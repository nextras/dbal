<?php

use Nextras\Dbal\Connection;
use Nextras\Dbal\Utils\FileImporter;


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}


$setupMode = TRUE;

echo "[setup] Purging temp.\n";
@mkdir(__DIR__ . '/../temp');
Tester\Helpers::purge(__DIR__ . '/../temp');


$config = parse_ini_file(__DIR__ . '/../databases.ini', TRUE);

foreach ($config as $name => $configDatabase) {
	echo "[setup] Bootstraping '{$name}' structure.\n";

	$connection = new Connection($configDatabase);
	$resetFunction = require __DIR__ . "/../data/{$name}-reset.php";
	$resetFunction($connection, $configDatabase['database']);

	FileImporter::executeFile($connection, __DIR__ . "/../data/{$name}-init.sql");
}

echo "[setup] All done.\n\n";
