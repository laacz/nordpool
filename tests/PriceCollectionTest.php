<?php

test('transforms prices into 15min grid', function () {
    $prices = [
        new Price(0.123, new DateTimeImmutable('2025-10-04 10:00:00'), new DateTimeImmutable('2025-10-04 10:15:00'), 'LV', 15),
        new Price(0.145, new DateTimeImmutable('2025-10-04 10:15:00'), new DateTimeImmutable('2025-10-04 10:30:00'), 'LV', 15),
        new Price(0.167, new DateTimeImmutable('2025-10-04 10:30:00'), new DateTimeImmutable('2025-10-04 10:45:00'), 'LV', 15),
        new Price(0.189, new DateTimeImmutable('2025-10-04 10:45:00'), new DateTimeImmutable('2025-10-04 11:00:00'), 'LV', 15),
    ];

    $collection = new PriceCollection($prices);
    $grid = $collection->toGrid(new DateTimeZone('UTC'), false, 1.0);

    expect($grid)->toHaveKey('2025-10-04');
    expect($grid['2025-10-04'])->toHaveKey(10);
    expect($grid['2025-10-04'][10])->toBe([
        0 => 0.123,
        1 => 0.145,
        2 => 0.167,
        3 => 0.189,
    ]);
});

test('transforms prices into hourly grid by averaging quarters', function () {
    $prices = [
        new Price(0.100, new DateTimeImmutable('2025-10-04 10:00:00'), new DateTimeImmutable('2025-10-04 10:15:00'), 'LV', 15),
        new Price(0.120, new DateTimeImmutable('2025-10-04 10:15:00'), new DateTimeImmutable('2025-10-04 10:30:00'), 'LV', 15),
        new Price(0.140, new DateTimeImmutable('2025-10-04 10:30:00'), new DateTimeImmutable('2025-10-04 10:45:00'), 'LV', 15),
        new Price(0.160, new DateTimeImmutable('2025-10-04 10:45:00'), new DateTimeImmutable('2025-10-04 11:00:00'), 'LV', 15),
    ];

    $collection = new PriceCollection($prices);
    $grid = $collection->toGrid(new DateTimeZone('UTC'), true, 1.0);

    expect($grid['2025-10-04'][10])->toBe([
        0 => 0.13, // (0.1 + 0.12 + 0.14 + 0.16) / 4 = 0.13
    ]);
});

test('applies multiplier for VAT', function () {
    $prices = [
        new Price(0.100, new DateTimeImmutable('2025-10-04 10:00:00'), new DateTimeImmutable('2025-10-04 10:15:00'), 'LV', 15),
    ];

    $collection = new PriceCollection($prices);
    $grid = $collection->toGrid(new DateTimeZone('UTC'), false, 1.21);

    expect($grid['2025-10-04'][10][0])->toBe(0.121);
});

test('converts timezone correctly', function () {
    $prices = [
        new Price(0.100, new DateTimeImmutable('2025-10-04 10:00:00', new DateTimeZone('Europe/Berlin')), new DateTimeImmutable('2025-10-04 10:15:00'), 'LV', 15),
    ];

    $collection = new PriceCollection($prices);
    $rigaTz = new DateTimeZone('Europe/Riga');
    $grid = $collection->toGrid($rigaTz, false, 1.0);

    // Berlin 10:00 = Riga 11:00 (during standard time)
    expect($grid['2025-10-04'])->toHaveKey(11);
    expect($grid['2025-10-04'][11][0])->toBe(0.1);
});

test('handles empty price array', function () {
    $collection = new PriceCollection([]);
    $grid = $collection->toGrid(new DateTimeZone('UTC'), false, 1.0);

    expect($grid)->toBe([]);
});

test('handles partial hour data in hourly mode', function () {
    $prices = [
        new Price(0.100, new DateTimeImmutable('2025-10-04 10:00:00'), new DateTimeImmutable('2025-10-04 10:15:00'), 'LV', 15),
        new Price(0.120, new DateTimeImmutable('2025-10-04 10:15:00'), new DateTimeImmutable('2025-10-04 10:30:00'), 'LV', 15),
        // Only 2 quarters, not 4
    ];

    $collection = new PriceCollection($prices);
    $grid = $collection->toGrid(new DateTimeZone('UTC'), true, 1.0);

    // Should not average if less than 4 quarters
    expect($grid['2025-10-04'][10])->toBe([
        0 => 0.1,
        1 => 0.12,
    ]);
});

test('rounds values to 4 decimal places', function () {
    $prices = [
        new Price(0.123456789, new DateTimeImmutable('2025-10-04 10:00:00'), new DateTimeImmutable('2025-10-04 10:15:00'), 'LV', 15),
    ];

    $collection = new PriceCollection($prices);
    $grid = $collection->toGrid(new DateTimeZone('UTC'), false, 1.0);

    expect($grid['2025-10-04'][10][0])->toBe(0.1235);
});

test('handles multiple days', function () {
    $prices = [
        new Price(0.100, new DateTimeImmutable('2025-10-04 10:00:00'), new DateTimeImmutable('2025-10-04 10:15:00'), 'LV', 15),
        new Price(0.200, new DateTimeImmutable('2025-10-05 10:00:00'), new DateTimeImmutable('2025-10-05 10:15:00'), 'LV', 15),
    ];

    $collection = new PriceCollection($prices);
    $grid = $collection->toGrid(new DateTimeZone('UTC'), false, 1.0);

    expect($grid)->toHaveKeys(['2025-10-04', '2025-10-05']);
    expect($grid['2025-10-04'][10][0])->toBe(0.1);
    expect($grid['2025-10-05'][10][0])->toBe(0.2);
});

test('hourly mode preserves rounding precision', function () {
    $prices = [
        new Price(0.1111, new DateTimeImmutable('2025-10-04 10:00:00'), new DateTimeImmutable('2025-10-04 10:15:00'), 'LV', 15),
        new Price(0.1112, new DateTimeImmutable('2025-10-04 10:15:00'), new DateTimeImmutable('2025-10-04 10:30:00'), 'LV', 15),
        new Price(0.1113, new DateTimeImmutable('2025-10-04 10:30:00'), new DateTimeImmutable('2025-10-04 10:45:00'), 'LV', 15),
        new Price(0.1114, new DateTimeImmutable('2025-10-04 10:45:00'), new DateTimeImmutable('2025-10-04 11:00:00'), 'LV', 15),
    ];

    $collection = new PriceCollection($prices);
    $grid = $collection->toGrid(new DateTimeZone('UTC'), true, 1.0);

    // Average = 0.11125, should round to 0.1113
    expect($grid['2025-10-04'][10][0])->toBe(0.1113);
});
