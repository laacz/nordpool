<?php

class Request
{
    public function __construct(
        private array $get = [],
        private array $server = []
    ) {
        $this->get = $get ?: $_GET;
        $this->server = $server ?: $_SERVER;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function uri(): string
    {
        return trim($this->server['REQUEST_URI'] ?? '', '/');
    }

    public function path(): string
    {
        return rtrim(explode('?', $this->uri())[0], '/');
    }

    public function has(string $key): bool
    {
        return isset($this->get[$key]);
    }

    public function getCurrentQueryString($vat = false, $resolution = 60): string
    {
        $args = [];
        if ($vat) {
            $args[] = 'vat';
        }
        if ($resolution === 60) {
            $args[] = 'res=60';
        }
        if (empty($args)) {
            return '';
        }

        return '?' . implode('&', $args);
    }
}
