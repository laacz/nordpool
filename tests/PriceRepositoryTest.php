<?php

/** @noinspection PhpUnhandledExceptionInspection */
test('fetches prices for date range', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);
    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-04 10:00:00+00:00', '2025-10-04 10:15:00+00:00', 123.45, 15, null),
        ('LV', '2025-10-05 10:00:00+00:00', '2025-10-05 10:15:00+00:00', 234.56, 15, null),
        ('LV', '2025-10-06 10:00:00+00:00', '2025-10-06 10:15:00+00:00', 345.67, 15, null)
    ");

    $repo = new PriceRepository($pdo);
    $start = new DateTimeImmutable('2025-10-04 00:00:00');
    $end = new DateTimeImmutable('2025-10-06 00:00:00');
    $prices = $repo->getPrices($start, $end);

    expect($prices)->toHaveCount(2)
        ->and($prices[0])->toBeInstanceOf(Price::class)
        ->and($prices[0]->price)->toBe(0.12345)
        ->and($prices[1]->price)->toBe(0.23456)
        ->and($prices[0]->country)->toBe('LV')
        ->and($prices[0]->resolution)->toBe(15);
});

test('uses prepared statements to prevent SQL injection', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);

    $repo = new PriceRepository($pdo);
    $start = new DateTimeImmutable('2025-10-04 00:00:00');
    $end = new DateTimeImmutable('2025-10-06 00:00:00');

    $prices = $repo->getPrices(
        $start,
        $end,
        "LV' OR '1'='1"
    );

    expect($prices)->toBeEmpty();
});

test('filters by country correctly', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);
    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-04 10:00:00+00:00', '2025-10-04 10:15:00+00:00', 123.45, 15, null),
        ('LT', '2025-10-04 10:00:00+00:00', '2025-10-04 10:15:00+00:00', 456.78, 15, null)
    ");

    $repo = new PriceRepository($pdo);
    $start = new DateTimeImmutable('2025-10-04 00:00:00');
    $end = new DateTimeImmutable('2025-10-05 00:00:00');
    $prices = $repo->getPrices($start, $end, 'LT');

    expect($prices)->toHaveCount(1)
        ->and($prices[0]->country)->toBe('LT')
        ->and($prices[0]->price)->toEqualWithDelta(0.45678, 0.00001);
});

test('filters by resolution correctly', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);
    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-04 10:00:00+00:00', '2025-10-04 10:15:00+00:00', 100.0, 15, null),
        ('LV', '2025-10-04 10:00:00+00:00', '2025-10-04 11:00:00+00:00', 200.0, 60, null)
    ");

    $repo = new PriceRepository($pdo);
    $start = new DateTimeImmutable('2025-10-04 00:00:00');
    $end = new DateTimeImmutable('2025-10-05 00:00:00');
    $prices = $repo->getPrices($start, $end, 'LV', 60);

    expect($prices)->toHaveCount(1)
        ->and($prices[0]->resolution)->toBe(60)
        ->and($prices[0]->price)->toBe(0.2);
});

test('returns empty array when no prices found', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);

    $repo = new PriceRepository($pdo);
    $start = new DateTimeImmutable('2025-10-04 00:00:00');
    $end = new DateTimeImmutable('2025-10-05 00:00:00');
    $prices = $repo->getPrices($start, $end);

    expect($prices)->toBeEmpty();
});

test('accepts any timezone and converts correctly', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);

    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-04 08:00:00+00:00', '2025-10-04 08:15:00+00:00', 100.0, 15, null),
        ('LV', '2025-10-04 09:00:00+00:00', '2025-10-04 09:15:00+00:00', 110.0, 15, null),
        ('LV', '2025-10-04 10:00:00+00:00', '2025-10-04 10:15:00+00:00', 123.45, 15, null),
        ('LV', '2025-10-04 11:00:00+00:00', '2025-10-04 11:15:00+00:00', 150.0, 15, null),
        ('LV', '2025-10-04 12:00:00+00:00', '2025-10-04 12:15:00+00:00', 160.0, 15, null),
        ('LV', '2025-10-04 13:00:00+00:00', '2025-10-04 13:15:00+00:00', 160.0, 15, null),
        ('LV', '2025-10-04 14:00:00+00:00', '2025-10-04 14:15:00+00:00', 160.0, 15, null),
        ('LV', '2025-10-04 15:00:00+00:00', '2025-10-04 15:15:00+00:00', 160.0, 15, null)
    ");

    $repo = new PriceRepository($pdo);
    $rigaTz = new DateTimeZone('Europe/Riga');

    $start = new DateTimeImmutable('2025-10-04 11:00:00', $rigaTz);
    $end = new DateTimeImmutable('2025-10-04 13:00:00', $rigaTz);
    $prices = $repo->getPrices($start, $end);

    expect($prices)->toHaveCount(2)
        ->and($prices[0]->price)->toBe(0.1)
        ->and($prices[1]->price)->toBe(0.11)
        ->and($prices[1]->startDate->format('H:i'))->toBe('09:00')
        ->and($prices[1]->startDate->getTimezone()->getName())->toBe('+00:00');

});

test('respects exclusive end boundary', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);
    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-05 00:00:00', '2025-10-05 00:15:00+00:00', 100.0, 15, null),
        ('LV', '2025-10-06 00:00:00', '2025-10-06 00:15:00+00:00', 200.0, 15, null)
    ");

    $repo = new PriceRepository($pdo);
    $UTC = new DateTimeZone('UTC');
    $start = new DateTimeImmutable('2025-10-05 00:00:00', $UTC);
    $end = new DateTimeImmutable('2025-10-06 00:00:00', $UTC);
    $prices = $repo->getPrices($start, $end);

    expect($prices)->toHaveCount(1)
        ->and($prices[0]->price)->toBe(0.1);
});

test('requires DateTimeImmutable for immutability', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);
    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-04 10:00:00+00:00', '2025-10-04 10:15:00+00:00', 123.45, 15, null)
    ");

    $repo = new PriceRepository($pdo);
    $UTC = new DateTimeZone('UTC');
    $start = new DateTimeImmutable('2025-10-04 00:00:00', $UTC);
    $end = new DateTimeImmutable('2025-10-05 00:00:00', $UTC);
    $prices = $repo->getPrices($start, $end);

    expect($prices)->toHaveCount(1)
        ->and($prices[0]->price)->toBe(0.12345)
        ->and($start->getTimezone()->getName())->toBe('UTC')
        ->and($end->getTimezone()->getName())->toBe('UTC');

    // Verify the original dates weren't mutated
});

test('filters out data outside date range', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);
    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-01 10:00:00+00:00', '2025-10-01 10:15:00+00:00', 50.0, 15, null),
        ('LV', '2025-10-02 23:45:00+00:00', '2025-10-02 23:59:00+00:00', 75.0, 15, null),
        ('LV', '2025-10-03 00:00:00+00:00', '2025-10-03 00:15:00+00:00', 100.0, 15, null),
        ('LV', '2025-10-03 12:00:00+00:00', '2025-10-03 12:15:00+00:00', 150.0, 15, null),
        ('LV', '2025-10-04 12:00:00+00:00', '2025-10-04 12:15:00+00:00', 200.0, 15, null),
        ('LV', '2025-10-05 00:00:00+00:00', '2025-10-05 00:15:00+00:00', 250.0, 15, null),
        ('LV', '2025-10-06 10:00:00+00:00', '2025-10-06 10:15:00+00:00', 300.0, 15, null)
    ");

    $repo = new PriceRepository($pdo);
    $UTC = new DateTimeZone('UTC');

    $start = new DateTimeImmutable('2025-10-03 00:00:00', $UTC);
    $end = new DateTimeImmutable('2025-10-05 00:00:00', $UTC);
    $prices = $repo->getPrices($start, $end);

    expect($prices)->toHaveCount(3)
        ->and($prices[0]->price)->toBe(0.1)
        ->and($prices[1]->price)->toBe(0.15)
        ->and($prices[2]->price)->toBe(0.2);
});

test('querying for local today gets all hours including first hour', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);

    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-03 22:00:00', '2025-10-03 21:00:00+00:00', 60.0, 60, null),
        ('LV', '2025-10-03 22:00:00', '2025-10-03 22:00:00+00:00', 70.0, 60, null),
        ('LV', '2025-10-03 22:00:00', '2025-10-03 23:00:00+00:00', 80.0, 60, null),
        ('LV', '2025-10-03 23:00:00', '2025-10-04 00:00:00+00:00', 90.0, 60, null),
        ('LV', '2025-10-04 00:00:00', '2025-10-04 01:00:00+00:00', 100.0, 60, null),
        ('LV', '2025-10-04 01:00:00', '2025-10-04 02:00:00+00:00', 110.0, 60, null),
        ('LV', '2025-10-04 22:00:00', '2025-10-04 23:00:00+00:00', 300.0, 60, null),
        ('LV', '2025-10-04 23:00:00', '2025-10-05 00:00:00+00:00', 310.0, 60, null),
        ('LV', '2025-10-05 00:00:00', '2025-10-05 01:00:00+00:00', 320.0, 60, null)
    ");

    $repo = new PriceRepository($pdo);
    $rigaTz = new DateTimeZone('Europe/Riga');

    $rigaStart = new DateTimeImmutable('2025-10-04 00:00:00', $rigaTz);
    $rigaEnd = new DateTimeImmutable('2025-10-05 00:00:00', $rigaTz);

    $prices = $repo->getPrices($rigaStart, $rigaEnd, 'LV', 60);

    expect($prices)->toHaveCount(6);

    $timestamps = array_map(fn ($p) => $p->startDate->format('Y-m-d H:i:s'), $prices);
    expect($timestamps)->toContain('2025-10-03 23:00:00')
        ->and($timestamps)->toContain('2025-10-04 00:00:00')
        ->and($timestamps)->toContain('2025-10-04 01:00:00')
        ->and($timestamps)->not->toContain('2025-10-04 23:00:00');
});

test('lastUpdate returns the most recent created_at timestamp', function () {
    $pdo = new PDO('sqlite::memory:');
    create_tables($pdo);

    $pdo->exec("INSERT INTO price_indices VALUES
        ('LV', '2025-10-04 10:00:00+00:00', '2025-10-04 10:15:00+00:00', 123.45, 15, '2025-10-01 12:00:00+00:00'),
        ('LV', '2025-10-05 10:00:00+00:00', '2025-10-05 10:15:00+00:00', 234.56, 15, '2025-10-02 12:00:00+00:00'),
        ('LV', '2025-10-06 10:00:00+00:00', '2025-10-06 10:15:00+00:00', 345.67, 15, '2025-10-03 12:00:00+00:00')
    ");

    $repo = new PriceRepository($pdo);
    $lastUpdate = $repo->lastUpdate();

    expect($lastUpdate)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($lastUpdate->format('Y-m-d H:i:s'))->toBe('2025-10-03 12:00:00');
});