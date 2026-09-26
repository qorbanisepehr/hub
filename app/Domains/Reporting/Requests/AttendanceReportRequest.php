<?php

namespace App\Domains\Reporting\Requests;

use App\Domains\Reporting\Requests\Concerns\ParsesReportDates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * HTTP boundary for GET /api/reports/attendance. The window stays REQUIRED
 * (attendance is a historical report, not a live work list) and is given in
 * the standard list style: `date_from`/`date_to`, both calendars accepted
 * (Jalali or Gregorian, Persian digits tolerated). Pagination/search speak
 * the same list contract as every other endpoint.
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
            'date_from' => ['required', 'string'],
            'date_to' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sort' => ['nullable', 'string', 'in:date'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'filter' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @throws ValidationException when the window is inverted or unparseable.
     */
    public function attendanceFrom(): CarbonImmutable
    {
        return $this->parseReportDate('date_from');
    }

    /**
     * @throws ValidationException when the window is inverted or too wide.
     */
    public function attendanceTo(): CarbonImmutable
    {
        $from = $this->attendanceFrom();
        $to = $this->parseReportDate('date_to');

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'date_to' => __('messages.validation.report_date_order'),
            ]);
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'date_to' => __('messages.validation.report_range_too_long', ['days' => self::MAX_RANGE_DAYS]),
            ]);
        }

        return $to;
    }

    public function attendancePage(): int
    {
        return $this->integer('page', 1);
    }

    public function attendancePerPage(): int
    {
        return $this->integer('per_page', 50);
    }

    public function attendanceOrder(): string
    {
        return $this->string('order')->toString() === 'asc' ? 'asc' : 'desc';
    }

    public function attendanceFilter(): ?string
    {
        $filter = trim($this->string('filter')->toString());

        return $filter === '' ? null : $filter;
    }
}
