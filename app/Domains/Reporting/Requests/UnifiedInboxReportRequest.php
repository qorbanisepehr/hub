<?php

namespace App\Domains\Reporting\Requests;

use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxReport;
use App\Domains\Reporting\Requests\Concerns\ParsesReportDates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * HTTP boundary for GET /api/reports/unified-inbox.
 *
 * Standard list contract (the one every list endpoint speaks): pagination
 * (`page`/`per_page`), `sort`/`order`, global `filter`, exact-match column
 * filters with the `{id}_not` exclusion convention, and the audit-style
 * `date_from`/`date_to` window. Unlike attendance, the window is optional —
 * sources decide their own default look-back when omitted (§41).
 */
class UnifiedInboxReportRequest extends FormRequest
{
    use ParsesReportDates;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.UnifiedInboxReport::MAX_PER_PAGE],
            'sort' => ['nullable', 'string', 'in:received_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'filter' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:50'],
            'source_not' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:100'],
            'type_not' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'status_not' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ];
    }

    public function inboxPage(): int
    {
        return $this->integer('page', 1);
    }

    public function inboxPerPage(): int
    {
        return $this->integer('per_page', 50);
    }

    public function inboxSort(): string
    {
        return 'received_at';
    }

    public function inboxOrder(): string
    {
        return $this->string('order')->toString() === 'asc' ? 'asc' : 'desc';
    }

    public function inboxFilter(): ?string
    {
        return $this->trimmedOrNull('filter');
    }

    public function inboxSource(): ?string
    {
        return $this->trimmedOrNull('source');
    }

    public function inboxSourceNot(): ?string
    {
        return $this->trimmedOrNull('source_not');
    }

    public function inboxType(): ?string
    {
        return $this->trimmedOrNull('type');
    }

    public function inboxTypeNot(): ?string
    {
        return $this->trimmedOrNull('type_not');
    }

    public function inboxStatus(): ?string
    {
        return $this->trimmedOrNull('status');
    }

    public function inboxStatusNot(): ?string
    {
        return $this->trimmedOrNull('status_not');
    }

    public function inboxDateFrom(): ?CarbonImmutable
    {
        $raw = $this->string('date_from')->toString();

        return $raw === '' ? null : $this->parseReportDate('date_from');
    }

    /**
     * @throws ValidationException when the window is inverted or too wide.
     */
    public function inboxDateTo(): ?CarbonImmutable
    {
        $raw = $this->string('date_to')->toString();

        if ($raw === '') {
            return null;
        }

        $to = $this->parseReportDate('date_to');
        $from = $this->inboxDateFrom();

        if ($from !== null) {
            if ($to->lt($from)) {
                throw ValidationException::withMessages([
                    'date_to' => __('messages.validation.report_date_order'),
                ]);
            }

            if ($from->diffInDays($to) > AttendanceReportRequest::MAX_RANGE_DAYS) {
                throw ValidationException::withMessages([
                    'date_to' => __('messages.validation.report_range_too_long', ['days' => AttendanceReportRequest::MAX_RANGE_DAYS]),
                ]);
            }
        }

        return $to;
    }

    private function trimmedOrNull(string $key): ?string
    {
        $value = trim($this->string($key)->toString());

        return $value === '' ? null : $value;
    }
}
