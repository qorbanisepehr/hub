<?php

namespace App\Integrations\Rahkaran;

use App\Domains\Reporting\Reports\Attendance\AttendanceRecord;
use App\Support\External\PersianDateTime;
use App\Support\PersianText;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The mapping boundary (V2 §12): Rahkaran vocabulary (employee_no, in_time,
 * work_duration, Jalali datetimes) dies here and never enters Reporting.
 * If Rahkaran's schema changes, this is the only place that changes.
 */
final class RahkaranAttendanceMapper
{
    /**
     * Map one raw Rahkaran row to the internal record.
     *
     * @param  array<string, mixed>  $row  Raw external row.
     *
     * @throws InvalidArgumentException when required fields are missing/unparseable.
     */
    public function map(array $row): AttendanceRecord
    {
        $employeeNo = $row['employee_no'] ?? null;
        $date = $row['date'] ?? null;

        if (! is_string($employeeNo) || $employeeNo === '' || ! is_string($date) || $date === '') {
            throw new InvalidArgumentException('Rahkaran attendance row is missing employee_no or date.');
        }

        return new AttendanceRecord(
            employeeIdentifier: $employeeNo,
            date: $this->requireDate($date),
            entryAt: $this->dateTime($date, $row['in_time'] ?? null),
            exitAt: $this->dateTime($date, $row['out_time'] ?? null),
            workedMinutes: $this->workedMinutes($row['work_duration'] ?? null),
        );
    }

    private function requireDate(string $date): CarbonImmutable
    {
        $parsedDate = PersianDateTime::parse($date);

        if ($parsedDate === null) {
            throw new InvalidArgumentException(sprintf('Rahkaran attendance date [%s] could not be parsed.', $date));
        }

        return $parsedDate;
    }

    /**
     * The instant a recorded time denotes. A missing or blank external time
     * means "not recorded" — the record carries null, never midnight.
     */
    private function dateTime(?string $date, ?string $time): ?CarbonImmutable
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $parsedDate = PersianDateTime::parse($date);
        $withTime = PersianDateTime::parseTime($time);

        if ($parsedDate === null || $withTime === null) {
            return null;
        }

        return $parsedDate->setTime($withTime[0], $withTime[1], $withTime[2]);
    }

    private function workedMinutes(mixed $duration): ?int
    {
        if ($duration === null || $duration === '') {
            return null;
        }

        if (is_int($duration)) {
            return $duration;
        }

        $value = PersianText::foldDigits(trim((string) $duration));

        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Rahkaran work_duration [%s] is not an integer.', $value));
        }

        return (int) $value;
    }
}
