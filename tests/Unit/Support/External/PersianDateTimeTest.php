<?php

use App\Support\External\PersianDateTime;

it('parses a jalali date to the gregorian equivalent', function () {
    // 1402/03/11 == 2023-06-01
    expect(PersianDateTime::parse('1402/03/11')?->toDateString())->toBe('2023-06-01')
        // 1400/01/01 == 2021-03-21
        ->and(PersianDateTime::parse('1400/01/01')?->toDateString())->toBe('2021-03-21');
});

it('parses a gregorian date unchanged', function () {
    expect(PersianDateTime::parse('2023-06-01')?->toDateString())->toBe('2023-06-01')
        ->and(PersianDateTime::parse('2023/06/01')?->toDateString())->toBe('2023-06-01');
});

it('accepts persian digit glyphs', function () {
    expect(PersianDateTime::parse('۱۴۰۲/۰۳/۱۱')?->toDateString())->toBe('2023-06-01');
});

it('combines date and time in the source timezone', function () {
    $carbon = PersianDateTime::parse('1402/03/11', '08:30');

    expect($carbon?->format('H:i'))->toBe('08:30')
        ->and($carbon?->timezoneName)->toBe('Asia/Tehran');
});

it('parses times with seconds and persian digits', function () {
    expect(PersianDateTime::parseTime('۰۸:۳۰:۱۵'))->toBe([8, 30, 15])
        ->and(PersianDateTime::parseTime('8:05'))->toBe([8, 5, 0]);
});

it('returns null for empty inputs', function () {
    expect(PersianDateTime::parse(null))->toBeNull()
        ->and(PersianDateTime::parse('  '))->toBeNull()
        ->and(PersianDateTime::parseTime(null))->toBeNull();
});

it('round-trips through its own jalali formatter', function () {
    $original = PersianDateTime::parse('1402/03/11');

    expect(PersianDateTime::toJalaliDate($original))->toBe('1402/03/11');
});

it('rejects invalid dates and times', function () {
    PersianDateTime::parse('11-06-2023');
})->throws(InvalidArgumentException::class);

it('rejects an impossible jalali day', function () {
    PersianDateTime::parse('1402/13/01');
})->throws(InvalidArgumentException::class);

it('rejects out-of-range times', function () {
    PersianDateTime::parseTime('24:00');
})->throws(InvalidArgumentException::class);
