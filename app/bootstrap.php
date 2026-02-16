<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CORE_PATH', BASE_PATH . '/core');
define('CONFIG_PATH', BASE_PATH . '/config');
define('VIEW_PATH', APP_PATH . '/Views');

require APP_PATH . '/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\' => APP_PATH . '/',
        'Core\\' => CORE_PATH . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
            }
            return;
        }
    }
});
