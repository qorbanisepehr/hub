<?php

namespace App\Integrations\Rahkaran;

use App\Contracts\External\AttendanceGateway;
use App\Domains\Reporting\Reports\Attendance\AttendanceQuery;
use App\Domains\Reporting\Reports\Attendance\AttendanceResult;
use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Fake driver (V2 §71): serves deterministic, realistic sample rows through
 * the REAL mapper so the mapping boundary (Jalali dates, Rahkaran field
 * names) is exercised end-to-end. It implements the same AttendanceGateway
 * port a real SQL Server/API adapter will implement later — Reporting never
 * knows which driver is bound.
 */
final class FakeRahkaranAttendanceGateway implements AttendanceGateway
{
    /** Fake employee rows: [external personnel code, seed for variance]. */
    private const FAKE_EMPLOYEES = [
        ['personnel_code' => '1001', 'seed' => 0],
        ['personnel_code' => '1002', 'seed' => 1],
        ['personnel_code' => '1003', 'seed' => 2],
    ];

    /** Header the fake external source emits (what a real client would see). */
    public const EXTERNAL_HEADER = ['employee_no', 'date', 'in_time', 'out_time', 'work_duration'];

    public function __construct(
        private readonly RahkaranAttendanceMapper $mapper,
    ) {}

    public function attendance(AttendanceQuery $query): AttendanceResult
    {
        $generatedAt = CarbonImmutable::now(PersianDateTime::TIMEZONE);

        $rows = $this->rowsFor($query);

        $records = [];
        $failures = 0;

        foreach ($rows as $row) {
            try {
                $records[] = $this->mapper->map($row);
            } catch (InvalidArgumentException) {
                // The fake must never crash the report on its own data; a
                // bad row counts as an unmapped row instead.
                $failures++;
            }
        }

        return new AttendanceResult(
            report: 'attendance',
            generatedAt: $generatedAt,
            records: $records,
            freshness: 'live',
            metadata: [
                'driver' => 'fake',
                'source' => 'rahkaran',
                'rows' => count($rows),
                'unmapped_rows' => $failures,
            ],
        );
    }

    /**
     * Build the fake external rows for the query window: one row per
     * working day per employee, weekends absent entirely. Deterministic —
     * same window, same rows.
     *
     * @return list<array<string, string>>
     */
    private function rowsFor(AttendanceQuery $query): array
    {
        $rows = [];

        foreach (self::FAKE_EMPLOYEES as $employee) {
            if ($query->employeeIdentifier !== null
                && $employee['personnel_code'] !== $query->employeeIdentifier) {
                continue;
            }

            $cursor = $query->from->startOfDay();

            while ($cursor->lte($query->to->startOfDay())) {
                // Thu/Fri weekend: no row at all — exercises missing days
                // rather than zero-minute days.
                if (! in_array($cursor->dayOfWeek, [CarbonImmutable::THURSDAY, CarbonImmutable::FRIDAY], true)) {
                    $rows[] = $this->row($employee['personnel_code'], $cursor, $employee['seed']);
                }

                $cursor = $cursor->addDay();
            }
        }

        return $rows;
    }

    /**
     * One external-format row (Jalali date, separate times, latin digits)
     * exactly as a real Rahkaran source would emit it.
     *
     * @return array<string, string>
     */
    private function row(string $personnelCode, CarbonImmutable $day, int $seed): array
    {
        $dayOfMonth = (int) $day->format('j');

        $inMinute = 8 * 60 + (($seed * 7 + $dayOfMonth) % 20);
        $outMinute = 17 * 60 + (($seed * 5 + $dayOfMonth) % 20);

        return [
            'employee_no' => $personnelCode,
            'date' => PersianDateTime::toJalaliDate($day),
            'in_time' => sprintf('%02d:%02d', intdiv($inMinute, 60), $inMinute % 60),
            'out_time' => sprintf('%02d:%02d', intdiv($outMinute, 60), $outMinute % 60),
            'work_duration' => (string) ($outMinute - $inMinute - 60), // 1h lunch break
        ];
    }
}
