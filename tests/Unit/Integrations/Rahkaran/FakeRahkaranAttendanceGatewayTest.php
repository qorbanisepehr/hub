<?php

use App\Domains\Reporting\Reports\Attendance\AttendanceQuery;
use App\Integrations\Rahkaran\FakeRahkaranAttendanceGateway;
use App\Integrations\Rahkaran\RahkaranAttendanceMapper;
use Carbon\CarbonImmutable;

/**
 * 1402/03/14 (2023-06-04) is a Sunday — a full fake week Sun..Wed.
 */
function attendanceQuery(string $from = '2023-06-04', string $to = '2023-06-07', ?string $employee = null): AttendanceQuery
{
    return new AttendanceQuery(
        from: CarbonImmutable::parse($from, 'Asia/Tehran'),
        to: CarbonImmutable::parse($to, 'Asia/Tehran'),
        employeeIdentifier: $employee,
    );
}

it('produces one record per working day per employee', function () {
    $result = (new FakeRahkaranAttendanceGateway(new RahkaranAttendanceMapper))
        ->attendance(attendanceQuery());

    // 3 employees x 4 days (Sun..Wed, Thu/Fri excluded).
    expect($result->records)->toHaveCount(12)
        ->and($result->freshness)->toBe('live')
        ->and($result->metadata['unmapped_rows'])->toBe(0);
});

it('emits rows with the external header fields through the real mapper', function () {
    expect(FakeRahkaranAttendanceGateway::EXTERNAL_HEADER)->toBe(
        ['employee_no', 'date', 'in_time', 'out_time', 'work_duration'],
    );

    $result = (new FakeRahkaranAttendanceGateway(new RahkaranAttendanceMapper))
        ->attendance(attendanceQuery(employee: '1002'));

    expect($result->records)->toHaveCount(4)
        ->and($result->records[0]->employeeIdentifier)->toBe('1002')
        // Jalali source dates arrive converted to Gregorian Carbon.
        ->and($result->records[0]->date->toDateString())->toBe('2023-06-04')
        ->and($result->records[0]->entryAt)->not->toBeNull()
        ->and($result->records[0]->exitAt)->not->toBeNull()
        ->and($result->records[0]->workedMinutes)->toBeGreaterThan(0);
});

it('is deterministic for the same window', function () {
    $gateway = new FakeRahkaranAttendanceGateway(new RahkaranAttendanceMapper);

    $first = $gateway->attendance(attendanceQuery());
    $second = $gateway->attendance(attendanceQuery());

    $serialize = fn ($result) => array_map(
        fn ($record) => [$record->employeeIdentifier, $record->date->toDateString(), $record->entryAt?->format('H:i')],
        $result->records,
    );

    expect($serialize($first))->toBe($serialize($second));
});

it('skips thursday and friday entirely', function () {
    // 1402/03/18 (2023-06-08) is Thursday, 19th Friday.
    $result = (new FakeRahkaranAttendanceGateway(new RahkaranAttendanceMapper))
        ->attendance(attendanceQuery(from: '2023-06-07', to: '2023-06-10', employee: '1001'));

    $dates = array_map(fn ($record) => $record->date->toDateString(), $result->records);

    expect($dates)->toBe(['2023-06-07', '2023-06-10']); // Wed + Sun only.
});
