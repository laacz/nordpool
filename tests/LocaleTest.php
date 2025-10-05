<?php

test('formats dates according to locale', function () {
    $locale = new AppLocale(
        Config::getCountries('LV'),
        Config::getTranslations()
    );

    $date = new DateTimeImmutable('2025-10-04 15:30:00');
    $formatted = $locale->formatDate($date, 'd. MMM');

    expect($formatted)->toBeString();
    expect($formatted)->toContain('4');
});

test('translates messages', function () {
    $locale = new AppLocale(
        Config::getCountries('LV'),
        Config::getTranslations()
    );

    expect($locale->msg('Šodien'))->toBe('Šodien');
});

test('translates to Lithuanian', function () {
    $locale = new AppLocale(
        Config::getCountries('LT'),
        Config::getTranslations()
    );

    expect($locale->msg('Šodien'))->toBe('Šiandien');
});

test('translates to Estonian', function () {
    $locale = new AppLocale(
        Config::getCountries('EE'),
        Config::getTranslations()
    );

    expect($locale->msg('Šodien'))->toBe('Täna');
});

test('generates correct routes for default language', function () {
    $lv = new AppLocale(Config::getCountries('LV'), Config::getTranslations());

    expect($lv->route('/'))->toBe('/');
    expect($lv->route('/?vat'))->toBe('/?vat');
});

test('generates correct routes for non-default language', function () {
    $lt = new AppLocale(Config::getCountries('LT'), Config::getTranslations());

    expect($lt->route('/'))->toBe('/lt/');
    expect($lt->route('/?vat'))->toBe('/lt/?vat');
});

test('gets config values', function () {
    $locale = new AppLocale(
        Config::getCountries('LV'),
        Config::getTranslations()
    );

    expect($locale->get('code'))->toBe('LV');
    expect($locale->get('vat'))->toBe(0.21);
    expect($locale->get('missing', 'default'))->toBe('default');
});

test('formats messages with arguments', function () {
    $locale = new AppLocale(
        Config::getCountries('LV'),
        Config::getTranslations()
    );

    // Using disclaimer which has %s placeholders
    $result = $locale->msgf('disclaimer', 'csv1', 'csv2', 'rss');

    expect($result)->toContain('csv1');
    expect($result)->toContain('csv2');
    expect($result)->toContain('rss');
});
