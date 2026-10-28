<?php

namespace App\Support\Concerns;

use App\Support\External\PersianDateTime;
use App\Support\PersianText;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Shared parsing for list/report date filters: both calendars accepted
 * (Jalali or Gregorian, Persian digits tolerated). Lives on the
 * FormRequests — the typed query the caller receives is already parsed.
 *
 * It lives in `App\Support` rather than in the Reporting domain because
 * it carries no business ownership: any list endpoint with a date window
 * needs it, and keeping it inside Reporting made every other consumer
 * depend on that domain for one method.
 */
trait ParsesReportDates
{
    /**
     * @throws ValidationException when the value is unparseable.
     */
    protected function parseReportDate(string $key): CarbonImmutable
    {
        $raw = PersianText::foldDigits($this->string($key)->toString());

        try {
            $parsed = PersianDateTime::parse($raw);
        } catch (InvalidArgumentException) {
            $parsed = null;
        }

        return $parsed ?? throw ValidationException::withMessages([
            $key => __('messages.validation.report_date_format'),
        ]);
    }
}
