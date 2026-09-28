<?php

namespace Database\Factories\BoardMeeting;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\External\PersianDateTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardMeeting>
 */
class BoardMeetingFactory extends Factory
{
    public function definition(): array
    {
        $pdate = $this->faker->unique()->date('Y_m_d', '1410-01-01');

        return [
            'date' => PersianDateTime::parse(
                str_replace('_', '-', $pdate),
            )?->toDateString(),
            'pdate' => str_replace('_', '-', $pdate),
            'number' => null,
            'agenda' => null,
            'minutes' => null,
            'minutes_from_letters' => ['متن صورتجلسه نمونه'],
            'minutes_hash' => hash('sha256', 'متن صورتجلسه نمونه'),
            'minutes_from_heyat_modire' => null,
            'meta' => [],
            'synced_at' => now(),
        ];
    }
}
