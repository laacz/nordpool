<?php

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
