<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Support\Concerns\ParsesReportDates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * The board-meeting-specific half of GET /api/board-meetings. The
 * standard list contract (pagination, `sort`/`order` whitelist, global
 * `filter`) is `ListQuery`'s job in the controller and is deliberately
 * not re-declared here.
 *
 * What this request owns is the registry's own vocabulary: a Jalali
 * year, the date window, and the AND tag filter.
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
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'jalali_year' => ['nullable', 'integer', 'between:1300,1500'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ];
    }

    /**
     * The Jalali year the registry key falls in — the range a secretary
     * actually asks for («همهٔ جلسات ۱۴۰۳»), in the calendar the
     * registry is filed in.
     */
    public function listJalaliYear(): ?int
    {
        $year = $this->integer('jalali_year');

        return $year > 0 ? $year : null;
    }

    /**
     * Tag slugs to AND-filter on: only meetings carrying every slug.
     *
     * @return list<string>
     */
    public function listTagSlugs(): array
    {
        return array_values(array_filter(
            array_map(
                fn ($slug) => is_string($slug) ? trim($slug) : '',
                (array) $this->input('tags', []),
            ),
            fn (string $slug) => $slug !== '',
        ));
    }

    public function listDateFrom(): ?CarbonImmutable
    {
        return $this->string('date_from')->toString() === ''
            ? null
            : $this->parseReportDate('date_from');
    }

    /**
     * @throws ValidationException when the window is inverted.
     */
    public function listDateTo(): ?CarbonImmutable
    {
        if ($this->string('date_to')->toString() === '') {
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
