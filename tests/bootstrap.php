<?php
// Kernlogik-Tests laufen ohne WordPress.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Marberger\\Signage\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = dirname(__DIR__) . '/includes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});
date_default_timezone_set('UTC');
