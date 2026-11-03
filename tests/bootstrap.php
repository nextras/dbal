<?php declare(strict_types = 1);

namespace NextrasTests\Dbal;


use Tester\Environment;

require_once __DIR__ . '/../vendor/autoload.php';

define('TEMP_DIR', __DIR__ . '/temp');
date_default_timezone_set('Europe/Prague');

Environment::setup();

if (getenv(Environment::RUNNER)) {
	// Runner
	header('Content-type: text/plain');
	putenv('ANSICON=TRUE');

} elseif (PHP_SAPI === 'cli') {
	// CLI

} else {
	// Browser
}
