<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Support\Str;

/**
 * Produces a board meeting's summary from the meeting's own content.
 *
 * The domain owns the prompt because the domain owns the vocabulary. What
 * counts as a decision, what a topic is, and which tags the hub's tagging
 * vocabulary can actually hold are all BoardMeeting's questions, and none of
 * them can be answered inside the Ai domain without that domain learning what
 * a board meeting is. So this service names no provider and no model: it hands
 * a key and a prompt to {@see AiAgentRunner} and reads an artifact back, and
 * everything about *which* model ran is decided in Settings (D28).
 *
 * The prompt is assembled from `minutes` + `agenda` + `attendees` only (D21).
 * Registry columns such as `origin` and `provenance` describe where the row
 * came from, not what the board did, and feeding them to a model only invites
 * it to summarize the import rather than the meeting.
 */
class BoardMeetingSummarizer
{
    public function __construct(
        private readonly AiAgentRunner $runner,
    ) {}

    /**
     * @param  list<string>  $fields  the fields to fill; empty means all of
     *                                them, which is what a first run and the
     *                                bulk action both send
     * @param  string|null  $modelOverride  a model *name* chosen for this run
     *                                      from the UI; never a provider (D28)
     *
     * @throws AiNotConfigured when no provider can answer
     * @throws \ValueError when a field name is outside the vocabulary
     */
    public function summarize(
        BoardMeeting $meeting,
        array $fields = [],
        ?string $modelOverride = null,
    ): MeetingSummary {
        $requested = MeetingSummaryField::fromValues($fields);

        $run = $this->runner->run(
            key: MeetingSummaryAgent::REGISTRY_KEY,
            prompt: $this->promptFor($meeting),
            agentClass: MeetingSummaryAgent::class,
            modelOverride: $modelOverride,
            settings: ['fields' => array_map(
                fn (MeetingSummaryField $field): string => $field->value,
                $requested,
            )],
        );

        return MeetingSummary::fromRun($run, $requested);
    }

    /**
     * The meeting as one block of text, sectioned by what each part is for.
     *
     * Public so a caller that needs a different model for one run — the live
     * suite's reason — can build the same prompt without reimplementing what
     * belongs to this domain.
     *
     * Labels are in English and the content is not: the prompt is written for
     * a model, and mixing a translated label into the Persian minutes would
     * risk the answer following the label's language instead of the meeting's.
     */
    public function promptFor(BoardMeeting $meeting): string
    {
        $sections = [
            "Registry key: {$meeting->held_on_jalali}, session {$meeting->sequence} of that day.",
            $this->section('Title', $meeting->title),
            $this->section('Agenda', $this->agendaLines($meeting)),
            $this->section('Attendees', $this->attendeeLines($meeting)),
            $this->section('Minutes', $meeting->minutes),
        ];

        return implode("\n\n", array_filter($sections, fn (?string $s): bool => $s !== null && $s !== ''));
    }

    private function agendaLines(BoardMeeting $meeting): ?string
    {
        if ($meeting->agenda === []) {
            return null;
        }

        return collect($meeting->agenda)
            ->map(fn (AgendaItem $item): string => Str::padLeft((string) $item->position, 2, '0').'. '.$item->title)
            ->implode("\n");
    }

    /**
     * Presence is part of the record, so an absent member is marked rather
     * than dropped: who was in the room changes what a decision meant.
     */
    private function attendeeLines(BoardMeeting $meeting): ?string
    {
        if ($meeting->attendees === []) {
            return null;
        }

        return collect($meeting->attendees)
            ->map(fn (Attendee $attendee): string => sprintf(
                '%s (%s%s)',
                $attendee->name,
                $attendee->role->value,
                $attendee->isPresent ? '' : ', absent',
            ))
            ->implode("\n");
    }

    private function section(string $label, ?string $body): ?string
    {
        return $body === null || trim($body) === '' ? null : "{$label}:\n{$body}";
    }
}
