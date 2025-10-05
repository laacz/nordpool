<?php

/** @noinspection PhpUnhandledExceptionInspection */
test('renders view with data', function () {
    $view = new View(__DIR__ . '/fixtures');

    ob_start();
    $view->render('test_view', ['name' => 'World']);
    $output = ob_get_clean();

    expect($output)->toBe("Hello, World!\n");
});

test('isolates view data with extract', function () {
    $view = new View(__DIR__ . '/fixtures');

    ob_start();
    $view->render('test_view', ['name' => 'Alice']);
    $output = ob_get_clean();

    expect($output)->toBe("Hello, Alice!\n");
});

test('renders to string', function () {
    $view = new View(__DIR__ . '/fixtures');

    $output = $view->renderToString('test_view', ['name' => 'Bob']);

    expect($output)->toBe("Hello, Bob!\n");
});

test('throws exception for missing view', function () {
    $view = new View(__DIR__ . '/fixtures');

    expect(fn () => $view->render('nonexistent'))
        ->toThrow(Exception::class, 'View not found: nonexistent');
});

test('handles empty data array', function () {
    file_put_contents(__DIR__ . '/fixtures/empty_view.php', 'No data needed');

    $view = new View(__DIR__ . '/fixtures');
    $output = $view->renderToString('empty_view');

    expect($output)->toBe('No data needed');

    unlink(__DIR__ . '/fixtures/empty_view.php');
});

test('renders views from default path', function () {
    // Create a temporary view in the default views directory
    $defaultViewPath = __DIR__ . '/../views';
    $testFile = $defaultViewPath . '/default_test.php';
    file_put_contents($testFile, 'Default path');

    $view = new View;
    $output = $view->renderToString('default_test');

    expect($output)->toBe('Default path');

    unlink($testFile);
});
