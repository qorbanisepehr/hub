<?php

namespace App\Domains\Reporting\Requests\Concerns;

use App\Support\External\PersianDateTime;
use App\Support\PersianText;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Shared parsing for report date filters: both calendars accepted (Jalali
 * or Gregorian, Persian digits tolerated). Lives on the FormRequests — the
 * typed query the reports receive is already parsed (V2 §16).
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
