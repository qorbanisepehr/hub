<?php

namespace App\Integrations\Bizagi;

use App\Contracts\External\InboxGateway;
use Carbon\CarbonImmutable;

/**
 * Fake driver (V2 §71): deterministic Bizagi BPM tasks in the external
 * vocabulary (case numbers, OPEN/CLOSED states, ISO datetimes) pushed
 * through the real mapper.
 */
final class FakeBizagiInboxGateway implements InboxGateway
{
    public function __construct(
        private readonly BizagiInboxMapper $mapper,
    ) {}

    public function inbox(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $start = $from ?? CarbonImmutable::today(self::TIMEZONE)->subDays(14);
        $end = $to ?? CarbonImmutable::today(self::TIMEZONE);

        $items = [];
        $cursor = $start->startOfDay();

        while ($cursor->lte($end->startOfDay())) {
            // BPM tasks land every day, including weekends — different
            // cadence than the office systems, richer merge ordering.
            foreach ($this->rows($cursor) as $row) {
                $items[] = $this->mapper->map($row);
            }

            $cursor = $cursor->addDay();
        }

        return $items;
    }

    private const TIMEZONE = 'Asia/Tehran';

    /**
     * @return list<array<string, string>>
     */
    private function rows(CarbonImmutable $day): array
    {
        $dayOfMonth = (int) $day->format('j');

        return [
            [
                'case_number' => sprintf('BZ-%02d%02d-1', (int) $day->format('m'), $dayOfMonth),
                'task_name' => 'تایید درخواست استخدام',
                'process_name' => 'عقد قرارداد',
                'sender_username' => '',
                'process_display' => 'فرآیند استخدام',
                'creation_date' => $day->setTime(11, 25)->toIso8601String(),
                'task_state' => $dayOfMonth % 2 === 0 ? 'OPEN' : 'CLOSED',
            ],
            [
                'case_number' => sprintf('BZ-%02d%02d-2', (int) $day->format('m'), $dayOfMonth),
                'task_name' => 'ثبت معرفی‌نامه',
                'process_name' => 'عقد قرارداد',
                'sender_username' => '',
                'process_display' => 'فرآیند استخدام',
                'creation_date' => $day->setTime(16, 5)->toIso8601String(),
                'task_state' => $dayOfMonth % 3 === 0 ? 'OPEN' : 'CLOSED',
            ],
        ];
    }
}
