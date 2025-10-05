<?php

test('returns all countries', function () {
    $countries = Config::getCountries();

    expect($countries)->toHaveKeys(['LV', 'LT', 'EE'])
        ->and($countries)->toHaveCount(3);
});

test('returns specific country config', function () {
    $lv = Config::getCountries('LV');

    expect($lv['code'])->toBe('LV')
        ->and($lv['code_lc'])->toBe('lv')
        ->and($lv['vat'])->toBe(0.21)
        ->and($lv['timezone'])->toBe('Europe/Riga');
});

test('returns default country for invalid code', function () {
    $default = Config::getCountries('XX');

    expect($default['code'])->toBe('LV');
});

test('returns default country for null', function () {
    $default = Config::getCountries('INVALID');

    expect($default['code'])->toBe('LV');
});

test('returns translations for all supported languages', function () {
    $translations = Config::getTranslations();

    expect($translations)->toBeArray()
        ->and($translations)->toHaveKey('Šodien')
        ->and($translations['Šodien'])->toHaveKeys(['LV', 'LT', 'EE']);
});

test('each country has required fields', function () {
    $countries = Config::getCountries();

    foreach ($countries as $code => $config) {
        expect($config)->toHaveKeys(['code', 'code_lc', 'name', 'flag', 'locale', 'timezone', 'vat'])
            ->and($config['code'])->toBe($code);
    }
});

test('VAT rates are correct per country', function () {
    expect(Config::getCountries('LV')['vat'])->toBe(0.21)
        ->and(Config::getCountries('LT')['vat'])->toBe(0.21)
        ->and(Config::getCountries('EE')['vat'])->toBe(0.20);
});
