<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\Reporting\Requests\Concerns\ParsesReportDates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * HTTP boundary for GET /api/board-meetings. Standard list contract:
 * pagination, `sort`/`order` (whitelisted), global `filter`, and the
 * optional `date_from`/`date_to` window (both calendars accepted — the
 * archive is addressed by Jalali dates, the client sends Gregorian).
 */
class BoardMeetingListRequest extends FormRequest
{
    use ParsesReportDates;

    public function authorize(): bool
    {
        // Capability check is delegated to the permission middleware.
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sort' => ['nullable', 'string', 'in:pdate,number'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'filter' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ];
    }

    public function listPage(): int
    {
        return $this->integer('page', 1);
    }

    public function listPerPage(): int
    {
        return $this->integer('per_page', 20);
    }

    public function listSort(): string
    {
        return $this->string('sort')->toString() === 'number' ? 'number' : 'pdate';
    }

    public function listOrder(): string
    {
        return $this->string('order')->toString() === 'asc' ? 'asc' : 'desc';
    }

    public function listFilter(): ?string
    {
        $filter = $this->string('filter')->toString();

        return $filter === '' ? null : $filter;
    }

    public function listDateFrom(): ?CarbonImmutable
    {
        $raw = $this->string('date_from')->toString();

        return $raw === '' ? null : $this->parseReportDate('date_from');
    }

    /**
     * @throws ValidationException when the window is inverted.
     */
    public function listDateTo(): ?CarbonImmutable
    {
        $raw = $this->string('date_to')->toString();

        if ($raw === '') {
            return null;
        }

        $to = $this->parseReportDate('date_to');
        $from = $this->listDateFrom();

        if ($from !== null && $to->lt($from)) {
            throw ValidationException::withMessages([
                'date_to' => __('messages.validation.report_date_order'),
            ]);
        }

        return $to;
    }
}
