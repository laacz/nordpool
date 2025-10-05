<?php

test('matches empty path pattern', function () {
    $router = new Router;
    $matched = false;

    $router->get('', function () use (&$matched) {
        $matched = true;
    });

    $request = new Request([], ['REQUEST_URI' => '/']);
    $router->dispatch($request);

    expect($matched)->toBeTrue();
});

test('matches path with country parameter', function () {
    $router = new Router;
    $capturedCountry = null;

    $router->get('{country}', function (Request $request, array $params) use (&$capturedCountry) {
        $capturedCountry = $params['country'];
    });

    $request = new Request([], ['REQUEST_URI' => '/lt']);
    $router->dispatch($request);

    expect($capturedCountry)->toBe('lt');
});

test('passes request and params to handler', function () {
    $router = new Router;
    $receivedRequest = null;
    $receivedParams = null;

    $router->get('{country}', function (Request $request, array $params) use (&$receivedRequest, &$receivedParams) {
        $receivedRequest = $request;
        $receivedParams = $params;
    });

    $request = new Request(['test' => 'value'], ['REQUEST_URI' => '/ee']);
    $router->dispatch($request);

    expect($receivedRequest)->toBe($request)
        ->and($receivedParams)->toHaveKey('country')
        ->and($receivedParams['country'])->toBe('ee');
});

test('calls first matching route', function () {
    $router = new Router;
    $called = [];

    $router->get('', function () use (&$called) {
        $called[] = 'first';
    });

    $router->get('', function () use (&$called) {
        $called[] = 'second';
    });

    $request = new Request([], ['REQUEST_URI' => '/']);
    $router->dispatch($request);

    expect($called)->toBe(['first']);
});

test('extracts multiple parameters', function () {
    $router = new Router;
    $params = null;

    $router->get('{country}/{city}', function (Request $request, array $p) use (&$params) {
        $params = $p;
    });

    $request = new Request([], ['REQUEST_URI' => '/lv/riga']);
    $router->dispatch($request);

    expect($params)->toHaveKey('country')
        ->and($params)->toHaveKey('city')
        ->and($params['country'])->toBe('lv')
        ->and($params['city'])->toBe('riga');
});

test('matches RSS route without country', function () {
    $router = new Router;
    $matched = false;

    $router->get('rss', function () use (&$matched) {
        $matched = true;
    });

    $request = new Request([], ['REQUEST_URI' => '/rss']);
    $router->dispatch($request);

    expect($matched)->toBeTrue();
});

test('matches RSS route with country parameter', function () {
    $router = new Router;
    $capturedCountry = null;

    $router->get('{country}/rss', function (Request $request, array $params) use (&$capturedCountry) {
        $capturedCountry = $params['country'];
    });

    $request = new Request([], ['REQUEST_URI' => '/lt/rss']);
    $router->dispatch($request);

    expect($capturedCountry)->toBe('lt');
});
