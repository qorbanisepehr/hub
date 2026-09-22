<?php

use App\Integrations\Rahkaran\RahkaranAttendanceMapper;

/**
 * External rows exactly as the (fake) Rahkaran source emits them.
 */
function rahkaranRow(array $overrides = []): array
{
    return array_merge([
        'employee_no' => '1001',
        'date' => '1402/03/11', // 2023-06-01
        'in_time' => '08:15',
        'out_time' => '17:30',
        'work_duration' => '495',
    ], $overrides);
}

it('maps a Jalali rahkaran row to the internal attendance record', function () {
    $record = (new RahkaranAttendanceMapper)->map(rahkaranRow());

    expect($record->employeeIdentifier)->toBe('1001')
        ->and($record->date->toDateString())->toBe('2023-06-01')
        ->and($record->entryAt?->format('H:i'))->toBe('08:15')
        ->and($record->exitAt?->format('H:i'))->toBe('17:30')
        ->and($record->workedMinutes)->toBe(495);
});

it('maps a gregorian rahkaran row identically', function () {
    $record = (new RahkaranAttendanceMapper)->map(rahkaranRow([
        'date' => '2023-06-01',
    ]));

    expect($record->date->toDateString())->toBe('2023-06-01')
        ->and($record->entryAt?->format('H:i'))->toBe('08:15')
        ->and($record->exitAt?->format('H:i'))->toBe('17:30');
});

it('folds persian digit glyphs before parsing', function () {
    $record = (new RahkaranAttendanceMapper)->map(rahkaranRow([
        'date' => '۱۴۰۲/۰۳/۱۱',
        'in_time' => '۰۸:۱۵',
        'work_duration' => '۴۹۵',
    ]));

    expect($record->date->toDateString())->toBe('2023-06-01')
        ->and($record->entryAt?->format('H:i'))->toBe('08:15')
        ->and($record->workedMinutes)->toBe(495);
});

it('accepts missing out_time and work_duration as nulls', function () {
    $record = (new RahkaranAttendanceMapper)->map(rahkaranRow([
        'out_time' => '',
        'work_duration' => null,
    ]));

    expect($record->exitAt)->toBeNull()
        ->and($record->workedMinutes)->toBeNull()
        ->and($record->entryAt?->format('H:i'))->toBe('08:15');
});

it('rejects a row without employee_no', function () {
    (new RahkaranAttendanceMapper)->map(rahkaranRow(['employee_no' => '']));
})->throws(InvalidArgumentException::class);

it('rejects an unrecognized date format', function () {
    (new RahkaranAttendanceMapper)->map(rahkaranRow(['date' => '11-06-2023']));
})->throws(InvalidArgumentException::class);

it('rejects a non-numeric work_duration', function () {
    (new RahkaranAttendanceMapper)->map(rahkaranRow(['work_duration' => '8h30m']));
})->throws(InvalidArgumentException::class);
