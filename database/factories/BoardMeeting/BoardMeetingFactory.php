<?php

namespace Database\Factories\BoardMeeting;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardMeeting>
 */
class BoardMeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->date(),
            'title' => null,
            'status' => BoardMeeting::STATUS_HELD,
            'number' => null,
            'minutes_text' => 'متن صورتجلسه نمونه',
            'agenda' => null,
            'attendees' => null,
            'summary' => null,
            'meta' => [],
            'synced_at' => now(),
        ];
    }
}
