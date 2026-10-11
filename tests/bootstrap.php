<?php

/** Load the package checkout when tests run in the shared development workspace. */
$autoload = getenv('POST_TEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
if (! is_file($autoload)) {
    $autoload = dirname(__DIR__, 3).'/vendor/autoload.php';
}
require $autoload;

spl_autoload_register(static function (string $class): void {
    $prefix = 'JobMetric\\Post\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $directory = dirname(__DIR__).'/src/';
    if (str_starts_with($relative, 'Factories\\')) {
        $directory = dirname(__DIR__).'/database/factories/';
        $relative = substr($relative, strlen('Factories\\'));
    }
    $path = $directory.str_replace('\\', '/', $relative).'.php';
    if (is_file($path)) {
        require $path;
    }
}, true, true);
