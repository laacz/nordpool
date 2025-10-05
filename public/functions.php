<?php

// Simple PSR-4 autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__.'/../src/'.$class.'.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

if (php_sapi_name() == 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($uri !== '/' && file_exists(__DIR__.$uri)) {
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

class AppLocale
{
    private IntlDateFormatter $dateFormatter;

    public function __construct(
        public ?array $config,
        public ?array $translations,
        public ?string $country = 'LV',
    ) {
        if ($config === null || $this->translations === null) {
            $this->config = Config::getCountries($country);
            $this->translations = Config::getTranslations();
        }
        $this->dateFormatter = IntlDateFormatter::create(
            $this->config['locale'],
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Europe/Riga',
            null,
            'd.m.Y H:i'
        );
    }

    /*
    * Format a date/time string according to the specified format and locale in the $country['locale'].
    * Format string is
    * based on ICU DateTime format.
    *
    * @param mixed $time The date/time to format. Can be a DateTime object, timestamp, or string.
    * @param string $format The format string. Default is 'd.m.Y H:i'.
    * @return string The formatted date/time string.
    */
    public function formatDate(mixed $time, string $format = 'd.m.Y H:i'): string|false
    {
        if (! $this->dateFormatter->setPattern($format)) {
            return false;
        }

        return $this->dateFormatter->format($time);
    }

    public function get(string $key, mixed $default = null): string
    {
        return $this->config[$key] ?? $default;
    }

    public function msg(string $msg): string
    {
        return $this->translations[$msg][$this->config['code']] ?? $msg;
    }

    public function msgf(string $msg, ...$args): string
    {
        return vsprintf($this->msg($msg), $args);
    }

    public function route(string $route): string
    {
        $lang = match ($this->config['code']) {
            'LV' => '',
            default => $this->config['code_lc'].'/',
        };

        return '/'.$lang.ltrim($route, '/');
    }
}

class Cache
{
    const string DIR = __DIR__.'/../cache/';

    public static function get(string $key, mixed $default = null): mixed
    {
        $file = self::DIR.md5($key).'.cache';
        if (file_exists($file) && (time() - filemtime($file) < 3600)) {
            return unserialize(file_get_contents($file));
        }

        return $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $file = self::DIR.md5($key).'.cache';
        file_put_contents($file, serialize($value), LOCK_EX);
    }

    public static function delete(string $key): void
    {
        $file = self::DIR.md5($key).'.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function clear(): void
    {
        $files = glob(self::DIR.'*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

class Lock
{
    const string DIR = __DIR__.'/../cache/';

    private string $file;

    private $handle;

    public function __construct(string $key)
    {
        $this->file = self::DIR.md5($key).'.lock';
        $this->handle = fopen($this->file, 'w+');
    }

    public function __destruct()
    {
        $this->unlock();
        fclose($this->handle);
        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    public function lock(): bool
    {
        return flock($this->handle, LOCK_EX);
    }

    public function unlock(): bool
    {
        return flock($this->handle, LOCK_UN);
    }
}
