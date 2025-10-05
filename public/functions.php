<?php

// Simple PSR-4 autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

if (php_sapi_name() == 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
        return false;
    }
}

function dump(...$vars): void
{
    var_dump(...$vars);
}

function dd(...$vars): void
{
    $vars = func_get_args();
    foreach ($vars as $var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }
    exit(1);
}

function abort(int $code = 500, string $message = ''): void
{
    http_response_code($code);
    if ($message) {
        echo $message;
    }
    exit(1);
}
