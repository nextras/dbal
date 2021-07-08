<?php


use Nextras\Dbal\Connection;


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}


$setupMode = TRUE;

echo "[setup] Purging temp.\n";
@mkdir(__DIR__ . '/../temp');
Tester\Helpers::purge(__DIR__ . '/../temp');


$config = parse_ini_file(__DIR__ . '/../databases.ini', TRUE);
$processed = [];

foreach ($config as $name => $configDatabase) {
	$key = $configDatabase['port'] ?? $name;
	if (isset($processed[$key])) continue;

	$processed[$key] = true;
	echo "[setup] Bootstrapping '{$name}' structure.\n";

	$connection = new Connection($configDatabase);
	$platform = $connection->getPlatform();
	$platformName = $platform->getName();
	$resetFunction = require __DIR__ . "/../data/{$platformName}-reset.php";
	$resetFunction($connection, $configDatabase);

	$parser = $platform->createMultiQueryParser();
	foreach ($parser->parseFile(__DIR__ . "/../data/{$platformName}-init.sql") as $sql) {
		$connection->query('%raw', $sql);
	}
}

echo "[setup] All done.\n\n";
