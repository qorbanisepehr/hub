<?php

namespace App\Domains\Reporting\Requests;

use App\Domains\Reporting\Requests\Concerns\ParsesReportDates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * HTTP boundary for GET /api/reports/unified-inbox. Filters (§32): source,
 * type, status, and optional window. Unlike attendance, the window is
 * optional — sources decide their own default look-back when omitted.
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
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', 'max:50'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'max:100'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function inboxFrom(): ?CarbonImmutable
    {
        $raw = $this->string('from')->toString();

        return $raw === '' ? null : $this->parseReportDate('from');
    }

    /**
     * @throws ValidationException when the window is inverted or too wide.
     */
    public function inboxTo(): ?CarbonImmutable
    {
        $raw = $this->string('to')->toString();

        if ($raw === '') {
            return null;
        }

        $to = $this->parseReportDate('to');
        $from = $this->inboxFrom();

        if ($from !== null) {
            if ($to->lt($from)) {
                throw ValidationException::withMessages([
                    'to' => __('messages.validation.report_date_order'),
                ]);
            }

            if ($from->diffInDays($to) > AttendanceReportRequest::MAX_RANGE_DAYS) {
                throw ValidationException::withMessages([
                    'to' => __('messages.validation.report_range_too_long', ['days' => AttendanceReportRequest::MAX_RANGE_DAYS]),
                ]);
            }
        }

        return $to;
    }

    /**
     * @return list<string>
     */
    public function inboxSources(): array
    {
        return $this->strings('sources');
    }

    /**
     * @return list<string>
     */
    public function inboxTypes(): array
    {
        return $this->strings('types');
    }

    /**
     * @return list<string>
     */
    public function inboxStatuses(): array
    {
        return $this->strings('statuses');
    }

    public function inboxLimit(): ?int
    {
        $limit = $this->integer('limit');

        return $limit > 0 ? $limit : null;
    }

    /**
     * @return list<string>
     */
    private function strings(string $key): array
    {
        $values = [];

        foreach ((array) $this->input($key, []) as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
