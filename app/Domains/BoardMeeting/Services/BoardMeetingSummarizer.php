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
 * everything about *which* model ran is decided in Settings (D28). The optional
 * provider/model pair is the one thing it does name, and only because a person
 * picked it for one run in the UI — the same exception the runner's own
 * docblock makes. The pair is passed through unexamined: whether it could still
 * be served was answered by the request before the run was queued, and a name
 * that goes stale in the queue degrades to the agent's binding instead of
 * failing the job.
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
     * @param  string|null  $provider  a gateway chosen for this one run from the
     *                                 UI. Optional because naming it is not
     *                                 necessary: an unqualified model picks the
     *                                 gateway the rest of the ladder would have
     *                                 used, which is the ordinary case
     * @param  string|null  $model  a model chosen for this one run from the UI,
     *                              read on whichever provider the run resolves
     *                              to. Names rather than ids because a name is
     *                              unique only within a provider, and the id this
     *                              used to take also decided the provider — so
     *                              the two could never be disagreed with each
     *                              other and the choice could not be expressed
     *                              as "this model, on that gateway"
     *
     * @throws AiNotConfigured when no provider can answer
     * @throws \ValueError when a field name is outside the vocabulary
     */
    public function summarize(
        BoardMeeting $meeting,
        array $fields = [],
        ?string $provider = null,
        ?string $model = null,
        bool $merge = false,
    ): MeetingSummary {
        $requested = MeetingSummaryField::fromValues($fields);

        // What the record already holds, narrowed to this run's fields. Read
        // from the row rather than passed in, because the value that matters is
        // the one on disk: a caller holding a stale copy would have the model
        // reconcile against a text nobody ever accepted.
        $previous = $merge
            ? $this->currentValues($meeting, $requested)
            : [];

        $run = $this->runner->run(
            key: MeetingSummaryAgent::REGISTRY_KEY,
            prompt: $this->promptFor($meeting, $previous),
            agentClass: MeetingSummaryAgent::class,
            provider: $provider,
            model: $model,
            settings: [
                'fields' => array_map(
                    fn (MeetingSummaryField $field): string => $field->value,
                    $requested,
                ),
                'previous' => $previous,
            ],
        );

        return MeetingSummary::fromRun($run, $requested);
    }

    /**
     * The stored value of each requested field, for a reconciling run.
     *
     * Only fields the record actually holds. A field with no stored value has
     * nothing to reconcile against, and passing an empty one would tell the
     * model that a person had already accepted a blank.
     *
     * @param  list<MeetingSummaryField>  $requested
     * @return array<string, string|list<string>>
     */
    private function currentValues(BoardMeeting $meeting, array $requested): array
    {
        $stored = MeetingSummary::fromArray($meeting->summary);
        $values = [];

        foreach ($requested as $field) {
            $value = $stored?->values[$field->value] ?? null;

            if ($value === null || $value === []) {
                continue;
            }

            $values[$field->value] = $value;
        }

        return $values;
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
    public function promptFor(BoardMeeting $meeting, array $current = []): string
    {
        $sections = [
            "Registry key: {$meeting->held_on_jalali}, session {$meeting->sequence} of that day.",
            $this->currentRecordSection($current),
            $this->section('Title', $meeting->title),
            $this->section('Agenda', $this->agendaLines($meeting)),
            $this->section('Attendees', $this->attendeeLines($meeting)),
            $this->section('Minutes', $meeting->minutes),
        ];

        return implode("\n\n", array_filter($sections, fn (?string $s): bool => $s !== null && $s !== ''));
    }

    /**
     * The stored summary, as a section a reconciling run can read.
     *
     * Null for an ordinary run, so the prompt is byte-for-byte what it was
     * before this feature existed — a run that is not reconciling must not even
     * hint that an earlier draft exists, or the model starts treating the
     * stored text as something to preserve.
     *
     * @param  array<string, string|list<string>>  $current
     */
    private function currentRecordSection(array $current): ?string
    {
        if ($current === []) {
            return null;
        }

        $lines = ['CURRENT RECORD (accepted by a person; reconcile with it, do not copy it):'];

        foreach ($current as $field => $value) {
            $label = $field;

            $lines[] = is_array($value)
                ? $label.': '.implode(' | ', $value)
                : $label.': '.$value;
        }

        return implode("\n", $lines);
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
