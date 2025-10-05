<?php

test('formats price with split decimals', function () {
    $helper = new ViewHelper;
    $formatted = $helper->format(0.1234);

    expect($formatted)->toBe('0.12<span class="extra-decimals">34</span>');
});

test('formats whole numbers correctly', function () {
    $helper = new ViewHelper;
    $formatted = $helper->format(1.0);

    expect($formatted)->toContain('1.00');
});

test('calculates green color for minimum value', function () {
    $helper = new ViewHelper;
    $color = $helper->getColorPercentage(0.10, 0.10, 0.20);

    expect($color)->toBe('rgb(0,136,0)');
});

test('calculates red color for maximum value', function () {
    $helper = new ViewHelper;
    $color = $helper->getColorPercentage(0.20, 0.10, 0.20);

    expect($color)->toBe('rgb(170,0,0)');
});

test('calculates yellow for middle value', function () {
    $helper = new ViewHelper;
    $color = $helper->getColorPercentage(0.15, 0.10, 0.20);

    expect($color)->toBe('rgb(169,170,0)');
});

test('handles equal min and max', function () {
    $helper = new ViewHelper;
    $color = $helper->getColorPercentage(0.10, 0.10, 0.10);

    expect($color)->toBe('rgb(0,136,0)');
});

test('returns white for sentinel value', function () {
    $helper = new ViewHelper;
    $color = $helper->getColorPercentage(-9999, 0, 1);

    expect($color)->toBe('#fff');
});

test('provides legend colors', function () {
    $helper = new ViewHelper;
    $colors = $helper->getLegendColors();

    expect($colors)->toBeArray()
        ->and($colors)->toHaveCount(3)
        ->and($colors[0]['pct'])->toBe(0.0)
        ->and($colors[2]['pct'])->toBe(1.0);
});
