<?php

namespace App\Integrations\Automation;

use App\Contracts\External\InboxGateway;
use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;

/**
 * Fake driver (V2 §71): deterministic automation letters in the external
 * vocabulary (Jalali receive date+time, seen flag, cartable wording) pushed
 * through the real mapper, implementing the same InboxGateway port a real
 * API adapter will implement later.
 */
final class FakeAutomationInboxGateway implements InboxGateway
{
    /** Cartable buckets cycling across rows for realistic variety. */
    private const CARTABLE_CYCLE = ['جهت اقدام', 'جهت امضا', 'جهت اطلاع'];

    public function __construct(
        private readonly AutomationInboxMapper $mapper,
    ) {}

    public function inbox(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $start = $from ?? CarbonImmutable::today(PersianDateTime::TIMEZONE)->subDays(14);
        $end = $to ?? CarbonImmutable::today(PersianDateTime::TIMEZONE);

        $items = [];
        $cursor = $start->startOfDay();

        while ($cursor->lte($end->startOfDay())) {
            // Letters arrive on working days only.
            if (! in_array($cursor->dayOfWeek, [CarbonImmutable::THURSDAY, CarbonImmutable::FRIDAY], true)) {
                foreach ($this->row($cursor) as $row) {
                    $items[] = $this->mapper->map($row);
                }
            }

            $cursor = $cursor->addDay();
        }

        return $items;
    }

    /**
     * One external-format automation letter row for a given day. Deterministic.
     *
     * @return array<string, string|int>
     */
    private function row(CarbonImmutable $day): array
    {
        $dayOfMonth = (int) $day->format('j');

        // 3 letters per working day: 08:10, 10:30, 14:45 — variety across
        // cartable buckets and seen states, deterministic per day.
        $hourSlots = [8 * 60 + 10, 10 * 60 + 30, 14 * 60 + 45];

        $rows = [];

        foreach ($hourSlots as $index => $minute) {
            $rows[] = [
                'letter_no' => sprintf('LTR-%02d%02d-%d', (int) $day->format('m'), $dayOfMonth, $index + 1),
                'sender' => [
                    'اداره کل منابع انسانی',
                    'اداره امور اداری',
                    'اداره آموزش',
                ][$index],
                'subject' => sprintf('نامه شماره %d مورخ %s', $index + 1, PersianDateTime::toJalaliDate($day)),
                'kind' => 'نامه',
                'receive_date' => PersianDateTime::toJalaliDate($day),
                'receive_time' => sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60),
                'cartable' => self::CARTABLE_CYCLE[($dayOfMonth + $index) % 3],
                'seen' => ($dayOfMonth + $index) % 2,
            ];
        }

        return $rows;
    }
}
