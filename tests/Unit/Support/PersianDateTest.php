<?php

namespace Tests\Unit\Support;

use App\Support\PersianDate;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class PersianDateTest extends TestCase
{
    public function test_persian_locale_renders_the_persian_calendar_with_persian_digits(): void
    {
        $this->app->setLocale('fa');

        $formatted = PersianDate::format(CarbonImmutable::parse('2026-09-29 09:02:00'));

        $this->assertSame('۱۴۰۵/۰۷/۰۷ ۰۹:۰۲', $formatted);
    }

    public function test_other_locales_fall_back_to_the_gregorian_stamp(): void
    {
        $this->app->setLocale('en');

        $formatted = PersianDate::format(CarbonImmutable::parse('2026-09-29 09:02:00'));

        $this->assertSame('2026/09/29 09:02', $formatted);
    }

    public function test_fallback_pattern_is_configurable(): void
    {
        $this->app->setLocale('en');

        $formatted = PersianDate::format(
            CarbonImmutable::parse('2026-09-29 09:02:00'),
            'Y-m-d H:i:s',
        );

        $this->assertSame('2026-09-29 09:02:00', $formatted);
    }
}
