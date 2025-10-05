<?php

class Cache
{
    const string DIR = __DIR__ . '/../cache/';

    public static function get(string $key, mixed $default = null): mixed
    {
        $file = self::DIR . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file) < 3600)) {
            return unserialize(file_get_contents($file));
        }

        return $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $file = self::DIR . md5($key) . '.cache';
        file_put_contents($file, serialize($value), LOCK_EX);
    }

    public static function delete(string $key): void
    {
        $file = self::DIR . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function clear(): void
    {
        $files = glob(self::DIR . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
