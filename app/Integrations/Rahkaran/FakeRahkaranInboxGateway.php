<?php

namespace App\Integrations\Rahkaran;

use App\Contracts\External\InboxGateway;
use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;

/**
 * Fake driver (V2 §71): deterministic Rahkaran workflow requests in the
 * external vocabulary (request ids, cartable codes, Y/N viewed, Jalali flow
 * dates) pushed through the real mapper.
 */
final class FakeRahkaranInboxGateway implements InboxGateway
{
    private const CARTABLE_CYCLE = ['A', 'S', 'I'];

    public function __construct(
        private readonly RahkaranInboxMapper $mapper,
    ) {}

    public function inbox(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $start = $from ?? CarbonImmutable::today(PersianDateTime::TIMEZONE)->subDays(14);
        $end = $to ?? CarbonImmutable::today(PersianDateTime::TIMEZONE);

        $items = [];
        $cursor = $start->startOfDay();

        while ($cursor->lte($end->startOfDay())) {
            if (! in_array($cursor->dayOfWeek, [CarbonImmutable::THURSDAY, CarbonImmutable::FRIDAY], true)) {
                foreach ($this->rows($cursor) as $row) {
                    $items[] = $this->mapper->map($row);
                }
            }

            $cursor = $cursor->addDay();
        }

        return $items;
    }

    /**
     * @return list<array<string, string>>
     */
    private function rows(CarbonImmutable $day): array
    {
        $dayOfMonth = (int) $day->format('j');

        // 2 requests per working day — different rhythm than Automation's 3
        // letters so merge/sort ordering is actually exercised.
        $slots = [
            ['minute' => 9 * 60 + 5, 'kind' => 'درخواست مرخصی'],
            ['minute' => 13 * 60 + 40, 'kind' => 'ماموریت'],
        ];

        $rows = [];

        foreach ($slots as $index => $slot) {
            $rows[] = [
                'request_id' => sprintf('RH%02d%02d%d', (int) $day->format('m'), $dayOfMonth, $index + 1),
                'request_no' => sprintf('%05d-%d', 40000 + $dayOfMonth * 2 + $index, (int) $day->format('y')),
                'sender' => 'سیستم راهکاران',
                'sender_username' => $index === 0 ? 'admin' : '',
                'subject' => sprintf('درخواست %s مورخ %s', $slot['kind'], PersianDateTime::toJalaliDate($day)),
                'request_kind' => $slot['kind'],
                'flow_date' => PersianDateTime::toJalaliDate($day),
                'flow_time' => sprintf('%02d:%02d', intdiv($slot['minute'], 60), $slot['minute'] % 60),
                'cartable_state' => self::CARTABLE_CYCLE[($dayOfMonth + $index) % 3],
                'viewed' => ($dayOfMonth + $index) % 2 === 0 ? 'Y' : 'N',
            ];
        }

        return $rows;
    }
}
