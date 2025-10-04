<?php

test('gets query parameter', function () {
    $request = new Request(['foo' => 'bar'], []);
    expect($request->get('foo'))->toBe('bar');
});

test('returns default for missing parameter', function () {
    $request = new Request([], []);
    expect($request->get('missing', 'default'))->toBe('default');
});

test('parses URI path', function () {
    $request = new Request([], ['REQUEST_URI' => '/lv/something?foo=bar']);
    expect($request->path())->toBe('lv/something');
});

test('checks parameter existence', function () {
    $request = new Request(['vat' => ''], []);
    expect($request->has('vat'))->toBeTrue();
    expect($request->has('missing'))->toBeFalse();
});

test('gets server parameter', function () {
    $request = new Request([], ['HTTP_HOST' => 'example.com']);
    expect($request->server('HTTP_HOST'))->toBe('example.com');
});

test('returns default for missing server parameter', function () {
    $request = new Request([], []);
    expect($request->server('MISSING', 'default'))->toBe('default');
});

test('handles empty REQUEST_URI', function () {
    $request = new Request([], ['REQUEST_URI' => '']);
    expect($request->uri())->toBe('');
});

test('trims leading and trailing slashes from URI', function () {
    $request = new Request([], ['REQUEST_URI' => '/foo/bar/']);
    expect($request->uri())->toBe('foo/bar');
});

test('handles path without query string', function () {
    $request = new Request([], ['REQUEST_URI' => '/lv/']);
    expect($request->path())->toBe('lv');
});

test('handles root path', function () {
    $request = new Request([], ['REQUEST_URI' => '/']);
    expect($request->path())->toBe('');
});

test('handles resolution parameter as string', function () {
    $request = new Request(['res' => '60'], []);
    expect($request->get('res'))->toBe('60');
    expect($request->get('res') == '60')->toBeTrue();
});

test('handles multiple query parameters', function () {
    $request = new Request(['vat' => '', 'res' => '60', 'purge' => ''], []);
    expect($request->has('vat'))->toBeTrue();
    expect($request->has('res'))->toBeTrue();
    expect($request->has('purge'))->toBeTrue();
    expect($request->get('res'))->toBe('60');
});
