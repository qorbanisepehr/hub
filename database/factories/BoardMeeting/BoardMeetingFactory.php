<?php

namespace Database\Factories\BoardMeeting;

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardMeeting>
 */
class BoardMeetingFactory extends Factory
{
    protected $model = BoardMeeting::class;

    public function definition(): array
    {
        $heldOn = CarbonImmutable::parse($this->faker->unique()->date());

        return [
            'held_on' => $heldOn->toDateString(),
            'held_on_jalali' => str_replace('/', '-', PersianDateTime::toJalaliDate($heldOn)),
            'sequence' => 1,
            'title' => null,
            'minutes' => 'متن صورتجلسه نمونه',
            'agenda' => [],
            'attendees' => [],
            'summary' => null,
            'origin' => 'farzin',
            'provenance' => ['letter_ids' => [], 'session_ids' => []],
            'imported_at' => now(),
            'source_locked_at' => null,
        ];
    }

    public function heldOn(CarbonImmutable $date, int $sequence = 1): static
    {
        return $this->state(fn (): array => [
            'held_on' => $date->toDateString(),
            'held_on_jalali' => str_replace('/', '-', PersianDateTime::toJalaliDate($date)),
            'sequence' => $sequence,
        ]);
    }

    /** A row a person has taken over: locked against the source. */
    public function humanOwned(): static
    {
        return $this->state(fn (): array => [
            'title' => 'جلسهٔ ویرایش‌شده',
            'source_locked_at' => now(),
        ]);
    }

    /**
     * Attach `$count` approval documents: a document plus one usage whose
     * `metadata` carries `registry_number`/`title` — the only place a
     * meeting's مصوبات numbers live now (vault idea 567, order A).
     *
     * Numbers start at 1001/ص/1404 so tests can assert a known value.
     */
    public function withApprovalDocuments(int $count = 1): static
    {
        return $this->afterCreating(function (BoardMeeting $meeting) use ($count): void {
            for ($index = 0; $index < $count; $index++) {
                $document = Document::factory()->create([
                    'hash' => hash('sha256', "approval-{$meeting->id}-{$index}"),
                ]);

                DocumentUsage::create([
                    'document_id' => $document->id,
                    'entity_type' => BoardMeeting::class,
                    'entity_id' => $meeting->id,
                    'section_key' => null,
                    'field_key' => null,
                    'metadata' => [
                        'registry_number' => sprintf('%d/ص/1404', 1001 + $index),
                        'title' => sprintf('مصوبهٔ شمارهٔ %d', 1001 + $index),
                    ],
                ]);
            }
        });
    }

    public function withAgenda(AgendaItem ...$items): static
    {
        return $this->state(fn (): array => ['agenda' => array_values($items)]);
    }

    public function withAttendees(Attendee ...$attendees): static
    {
        return $this->state(fn (): array => ['attendees' => array_values($attendees)]);
    }

    public function chairedBy(string $name): static
    {
        return $this->withAttendees(
            new Attendee($name, AttendeeRole::Chair),
        );
    }
}
