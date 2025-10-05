<?php

/** @noinspection PhpUnhandledExceptionInspection */
test('RSS view renders with Price objects', function () {
    $view = new View(__DIR__ . '/../../views');
    $tz_utc = new DateTimeZone('UTC');
    $tz_local = new DateTimeZone('Europe/Riga');

    $prices = [
        new Price(
            price: 0.123,
            startDate: new DateTimeImmutable('2025-10-06 10:00:00')->setTimezone($tz_utc),
            endDate: new DateTimeImmutable('2025-10-06 10:15:00')->setTimezone($tz_utc),
            country: 'LV',
            resolution: 15
        ),
        new Price(
            price: 0.145,
            startDate: new DateTimeImmutable('2025-10-06 10:15:00')->setTimezone($tz_utc),
            endDate: new DateTimeImmutable('2025-10-06 10:30:00')->setTimezone($tz_utc),
            country: 'LV',
            resolution: 15
        ),
    ];

    ob_start();
    $view->render('rss', [
        'local_tomorrow_start' => new DateTimeImmutable('2025-10-06', $tz_local),
        'country' => 'LV',
        'last_update' => new DateTimeImmutable('2025-10-05 12:00:00', $tz_local),
        'data' => $prices,
        'tz_local' => $tz_local,
        'vat' => 0.21,
    ]);
    $output = ob_get_clean();

    expect($output)->toContain('<feed>')
        ->and($output)->toContain('Nordpool spot prices tomorrow')
        ->and($output)->toContain('<price>0.123</price>')
        ->and($output)->toContain('<price>0.145</price>')
        ->and($output)->toContain('<resolution>15</resolution>')
        ->and($output)->toContain('LV-15-');
});

test('RSS view renders with empty data', function () {
    $view = new View(__DIR__ . '/../../views');
    $tz_local = new DateTimeZone('Europe/Riga');

    ob_start();
    $view->render('rss', [
        'local_tomorrow_start' => new DateTimeImmutable('2025-10-06', $tz_local),
        'country' => 'LV',
        'last_update' => new DateTimeImmutable('2025-10-05 12:00:00', $tz_local),
        'data' => [],
        'tz_local' => $tz_local,
        'vat' => 0.21,
    ]);
    $output = ob_get_clean();

    expect($output)->toContain('<feed>')
        ->and($output)->toContain('2025-10-06')
        ->and($output)->not->toContain('<entry>');
});

test('index view renders with price data', function () {
    $view = new View(__DIR__ . '/../../views');

    $today = [
        0 => [0 => 0.12, 1 => 0.13, 2 => 0.14, 3 => 0.15],
        1 => [0 => 0.16, 1 => 0.17, 2 => 0.18, 3 => 0.19],
    ];

    $tomorrow = [
        0 => [0 => 0.22, 1 => 0.23, 2 => 0.24, 3 => 0.25],
    ];

    $locale = new AppLocale(
        Config::getCountries('LV'),
        Config::getTranslations()
    );

    ob_start();
    $view->render('index', [
        'locale' => $locale,
        'countryConfig' => Config::getCountries(),
        'country' => 'LV',
        'resolution' => 15,
        'with_vat' => false,
        'vat' => 0.21,
        'current_time' => new DateTimeImmutable('2025-10-05 12:00:00', new DateTimeZone('Europe/Riga')),
        'viewHelper' => new ViewHelper,
        'today' => $today,
        'tomorrow' => $tomorrow,
        'today_avg' => 0.15,
        'tomorrow_avg' => 0.235,
        'today_max' => 0.19,
        'today_min' => 0.12,
        'tomorrow_max' => 0.25,
        'tomorrow_min' => 0.22,
        'quarters_per_hour' => 4,
    ]);
    $output = ob_get_clean();

    expect($output)->toContain('<!doctype html>')
        ->and($output)->toContain('€/kWh')
        ->and($output)->toContain('desktop-table')
        ->and($output)->toContain('mobile-tables');
});
