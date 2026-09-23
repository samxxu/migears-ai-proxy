<?php

declare(strict_types=1);

// phpunit.xml.dist points bootstrap here. Composer's own install is blocked
// by the sandbox for new packages, so we reuse the sibling package's vendor
// for PHPUnit classes and register our own PSR-4 loader for MiGears\AiProxy.
require dirname(__DIR__, 2) . '/migears-http-exceptions/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'MiGears\\AiProxy\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});