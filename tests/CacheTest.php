<?php

beforeEach(function () {
    Cache::clear();
});

afterAll(function () {
    Cache::clear();
});

test('stores and retrieves values', function () {
    Cache::set('test', 'value');
    expect(Cache::get('test'))->toBe('value');
});

test('returns default for missing keys', function () {
    expect(Cache::get('missing', 'default'))->toBe('default');
});

test('clears all cache', function () {
    Cache::set('key1', 'value1');
    Cache::set('key2', 'value2');
    Cache::clear();

    expect(Cache::get('key1'))->toBeNull();
    expect(Cache::get('key2'))->toBeNull();
});

test('deletes specific key', function () {
    Cache::set('key1', 'value1');
    Cache::set('key2', 'value2');
    Cache::delete('key1');

    expect(Cache::get('key1'))->toBeNull();
    expect(Cache::get('key2'))->toBe('value2');
});

test('handles complex data types', function () {
    $data = ['foo' => 'bar', 'nested' => ['baz' => 123]];
    Cache::set('complex', $data);

    expect(Cache::get('complex'))->toBe($data);
});
