<?php

namespace Database\Factories\BoardMeeting;

use App\Domains\BoardMeeting\Models\Letter;
use App\Support\External\PersianDateTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Letter>
 */
class LetterFactory extends Factory
{
    public function definition(): array
    {
        $gregorian = $this->faker->date();

        return [
            'source_id' => $this->faker->unique()->numberBetween(1, 999999),
            'subject' => $this->faker->sentence(),
            'text' => $this->faker->paragraph(),
            'date' => $gregorian,
            'pdate' => null,
            'entity_number' => $this->faker->numerify('####'),
            'export_entity_number' => $this->faker->numerify('####'),
            'r_title' => 'مدیرعامل',
            'r_name' => $this->faker->name(),
            'company_name' => 'شرکت نمونه',
            'meeting_date' => null,
            'meeting_pdate' => null,
            'has_meeting' => false,
            'creator_role_id' => 2880,
            'original' => [],
            'source_last_edit_date' => now(),
            'synced_at' => now(),
        ];
    }

    /** A letter inviting to a board meeting on the given Jalali date. */
    public function meeting(string $pdate = '1404-06-15'): static
    {
        return $this->state(fn () => [
            'subject' => "دعوت به جلسه هیئت مدیره مورخ $pdate",
            'has_meeting' => true,
            'meeting_date' => PersianDateTime::parse($pdate)?->toDateString(),
            'meeting_pdate' => $pdate,
        ]);
    }
}
