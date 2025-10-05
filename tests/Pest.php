<?php

// Autoload src classes for tests
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

function create_tables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE price_indices (
        country TEXT,
        ts_start TEXT,
        ts_end TEXT,
        value REAL,
        resolution_minutes INTEGER,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(country, ts_start, ts_end, resolution_minutes)
    )');
}
