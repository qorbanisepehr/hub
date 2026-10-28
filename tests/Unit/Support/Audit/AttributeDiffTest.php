<?php

use App\Support\Audit\AttributeDiff;
use Tests\TestCase;

/*
 * Unit tests default to the plain PHPUnit case; bind the application
 * container so nothing this class imports surprises the test runner.
 */
uses(TestCase::class);

it('reports only the changed scalar leaves as old/new pairs', function () {
    $result = AttributeDiff::diff(
        ['title' => 'جلسه عادی', 'minutes' => 'متن صورتجلسه'],
        ['title' => 'جلسه فوق‌العاده', 'minutes' => 'متن صورتجلسه'],
    );

    expect($result['old'])->toBe(['title' => 'جلسه عادی'])
        ->and($result['new'])->toBe(['title' => 'جلسه فوق‌العاده']);
});

it('stays empty when nothing changed', function () {
    $before = ['title' => 'c', 'minutes' => 'd', 'attendees' => []];

    expect(AttributeDiff::diff($before, $before))->toBe(['old' => [], 'new' => []]);
});

it('flattens nested arrays into dotted leaf paths', function () {
    $flattened = AttributeDiff::flatten([
        'attendees' => [
            ['name' => 'علی', 'role' => 'chair'],
        ],
    ]);

    expect($flattened)->toBe([
        'attendees.0.name' => 'علی',
        'attendees.0.role' => 'chair',
    ]);
});

it('diffs inside lists at leaf granularity, not whole-list granularity', function () {
    $result = AttributeDiff::diff(
        ['attendees' => [
            ['name' => 'علی', 'role' => 'chair'],
            ['name' => 'مریم', 'role' => 'guest'],
        ]],
        ['attendees' => [
            ['name' => 'علی', 'role' => 'chair'],
            ['name' => 'مریم', 'role' => 'member'],
        ]],
    );

    expect($result['old'])->toBe(['attendees.1.role' => 'guest'])
        ->and($result['new'])->toBe(['attendees.1.role' => 'member']);
});

it('treats a removed leaf as a null change', function () {
    $result = AttributeDiff::diff(
        ['title' => 'توضیح', 'minutes' => 'متن'],
        ['minutes' => 'متن'],
    );

    expect($result['old'])->toBe(['title' => 'توضیح'])
        ->and($result['new'])->toBe(['title' => null]);
});
