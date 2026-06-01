<?php
/* Shared PSR-4 autoloader for the FastUuid\Compat package, for compat phpt
   tests (the package ships no committed vendor/autoload). */
spl_autoload_register(static function (string $class): void {
    $prefix = 'FastUuid\\Compat\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/compat/src/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});
