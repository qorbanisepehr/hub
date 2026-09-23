<?php

namespace App\Domains\Reporting\Requests;

use App\Domains\Reporting\Requests\Concerns\ParsesReportDates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * HTTP boundary for GET /api/reports/attendance. Accepts both calendars for
 * date filters (Jalali or Gregorian, Persian digits tolerated) — the client
 * is free to send either. Parsing lives here; the controller assembles the
 * typed query, keeping HTTP concerns out of the report (V2 §16).
 */
class AttendanceReportRequest extends FormRequest
{
    use ParsesReportDates;

    /** Max window the API will serve; keeps external source load explicit. */
    public const MAX_RANGE_DAYS = 92;

    public function authorize(): bool
    {
        // Capability check is delegated to the permission middleware;
        // data-level scope is applied in the controller (V2 §28).
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @throws ValidationException when the window is inverted or unparseable.
     */
    public function attendanceFrom(): CarbonImmutable
    {
        return $this->parseDate('from');
    }

    /**
     * @throws ValidationException when the window is inverted or unparseable.
     */
    public function attendanceTo(): CarbonImmutable
    {
        $from = $this->attendanceFrom();
        $to = $this->parseDate('to');

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to' => __('messages.validation.report_date_order'),
            ]);
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => __('messages.validation.report_range_too_long', ['days' => self::MAX_RANGE_DAYS]),
            ]);
        }

        return $to;
    }

    public function attendanceStatus(): ?string
    {
        $status = $this->string('status')->toString();

        return $status === '' ? null : $status;
    }

    private function parseDate(string $key): CarbonImmutable
    {
        return $this->parseReportDate($key);
    }
}
