<?php

require_once __DIR__ . '/../vendor/autoload.php';

$_ENV['APP_SECRET'] = $_ENV['APP_SECRET'] ?? 'test-secret';
$_ENV['APP_ENCRYPTION_KEY'] = $_ENV['APP_ENCRYPTION_KEY'] ?? 'test-encryption-key';
$_ENV['SESSION_COOKIE_NAME'] = $_ENV['SESSION_COOKIE_NAME'] ?? 'dotti_session';
$_ENV['SESSION_COOKIE_SECURE'] = $_ENV['SESSION_COOKIE_SECURE'] ?? 'false';

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../api/core/' . $class . '.php',
        __DIR__ . '/../api/controller/' . $class . '.php',
        __DIR__ . '/../api/model/' . $class . '.php',
        __DIR__ . '/../api/service/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
