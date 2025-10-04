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
