<?php

namespace App\Domains\BoardMeeting\Exports;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\Document\Models\DocumentUsage;
use App\Support\Exports\Contract\ProvidesTemplateLabels;
use App\Support\Exports\Templates\TemplateField;
use App\Support\Exports\Templates\TemplateFieldRegistry;
use App\Support\Exports\ValuePresenter;

/**
 * The values a board meeting sheet can be filled with, named.
 *
 * The companion to {@see BoardMeetingSheetExport}, and deliberately not a
 * replacement for it: that one decides what a sheet CONTAINS (sections, in an
 * order a template may reorder), this one decides what values a template may
 * ADDRESS. Both exist because Word lets a person lay the document out themselves,
 * and laying it out is only possible if the data is reachable by name.
 *
 * LABELS ARE THE DOMAIN'S OWN, reused from `print.fields.*` and `print.columns.*`
 * rather than written fresh. They are already the wording a reader of this
 * meeting's sheet sees, so a template's cheat sheet and the printed document
 * name the same thing in the same words — and a template author does not have to
 * learn a second vocabulary for the same field.
 *
 * SHORT LISTS ARE INLINE, NOT ROWS. `topics` and `key_decisions` are prose that
 * happens to be a list, and joining them with a separator reads correctly in a
 * sentence; giving each its own table row would put a border around what should
 * be a paragraph. Rows are for RECORD COLLECTIONS — people, agenda items,
 * attachments — where the count is not known in advance and each entry deserves
 * its own line.
 */
final class BoardMeetingTemplateFields implements ProvidesTemplateLabels, TemplateFieldRegistry
{
    /**
     * The summary's own lists, joined for a template that wants them in prose.
     *
     * @var list<string>
     */
    private const SUMMARY_LISTS = [
        'topics',
        'bullet_points',
        'key_decisions',
        'suggested_tags',
    ];

    public function domain(): string
    {
        return BoardMeetingSheetExport::TEMPLATE_DOMAIN;
    }

    /**
     * The two words a general template needs and cannot work out for itself.
     *
     * Both reuse wording this domain already prints. The title is the one the
     * meeting's own document carries, and the record's identity is the registry label
     * the model already builds for filenames and the document archive — so a template
     * that says "about {{domain_label}}, record {{entity_label}}" says the same thing
     * the same way as everything else about this meeting, rather than introducing a
     * fourth spelling of what a board meeting is.
     */
    public function domainLabel(): string
    {
        return __('board_meeting.print.title');
    }

    /**
     * The registry identity — the Jalali date plus the sequence — and never the row
     * id, because an id printed on a certificate is a number nobody can check
     * anything against.
     */
    public function entityLabel(object $entity): string
    {
        return $entity instanceof BoardMeeting ? $entity->registryLabel() : '';
    }

    /**
     * @return list<TemplateField>
     */
    public function fields(): array
    {
        return [
            TemplateField::inline('title', __('board_meeting.print.fields.title')),
            TemplateField::inline('held_on', __('board_meeting.print.fields.held_on')),
            TemplateField::inline('sequence', __('board_meeting.print.fields.sequence')),
            TemplateField::inline('origin', __('board_meeting.print.fields.origin')),
            TemplateField::inline('minutes', __('board_meeting.print.sections.minutes')),

            TemplateField::inline('summary.status', __('board_meeting.print.fields.status')),
            TemplateField::inline('summary.error', __('board_meeting.print.fields.error')),
            TemplateField::inline('summary.executive_summary', __('board_meeting.print.fields.executive_summary')),
            TemplateField::inline('summary.topics', __('board_meeting.print.fields.topics')),
            TemplateField::inline('summary.bullet_points', __('board_meeting.print.fields.bullet_points')),
            TemplateField::inline('summary.key_decisions', __('board_meeting.print.fields.key_decisions')),
            TemplateField::inline('summary.suggested_tags', __('board_meeting.print.fields.suggested_tags')),

            TemplateField::row('attendee.name', __('board_meeting.print.columns.name'), 'attendee'),
            TemplateField::row('attendee.role', __('board_meeting.print.columns.role'), 'attendee'),
            TemplateField::row('attendee.present', __('board_meeting.print.columns.present'), 'attendee'),

            TemplateField::row('agenda.position', __('board_meeting.print.columns.position'), 'agenda'),
            TemplateField::row('agenda.title', __('board_meeting.print.columns.agenda_title'), 'agenda'),

            TemplateField::row('tag.name', __('board_meeting.print.columns.tag'), 'tag'),

            TemplateField::row('approval.registry_number', __('board_meeting.print.columns.registry_number'), 'approval'),
            TemplateField::row('approval.document_title', __('board_meeting.print.columns.document_title'), 'approval'),

            TemplateField::row('attachment.file_name', __('board_meeting.print.columns.file_name'), 'attachment'),
            TemplateField::row('attachment.size', __('board_meeting.print.columns.size'), 'attachment'),
            TemplateField::row('attachment.mime', __('board_meeting.print.columns.mime'), 'attachment'),
            TemplateField::row('attachment.uploaded_at', __('board_meeting.print.columns.uploaded_at'), 'attachment'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(object $entity): array
    {
        $meeting = $entity instanceof BoardMeeting
            ? $entity
            : BoardMeeting::query()->with(['tags', 'documentUsages.document'])->findOrFail((int) $entity);

        $meeting->loadMissing(['tags', 'documentUsages.document']);

        $summary = (array) $meeting->summary;

        return [
            'title' => (string) $meeting->title,
            'held_on' => $meeting->held_on?->format('Y/m/d') ?? '',
            'sequence' => $meeting->sequence !== null ? (string) $meeting->sequence : '',
            'origin' => (string) $meeting->origin,
            'minutes' => (string) $meeting->minutes,

            'summary.status' => (string) ($summary['status'] ?? ''),
            'summary.error' => (string) ($summary['error'] ?? ''),
            'summary.executive_summary' => (string) ($summary['executive_summary'] ?? ''),
            'summary.topics' => $this->joinSummaryList($summary, 'topics'),
            'summary.bullet_points' => $this->joinSummaryList($summary, 'bullet_points'),
            'summary.key_decisions' => $this->joinSummaryList($summary, 'key_decisions'),
            'summary.suggested_tags' => $this->joinSummaryList($summary, 'suggested_tags'),

            'attendee' => array_map(
                fn (Attendee $attendee): array => [
                    'name' => $attendee->name,
                    'role' => $attendee->role->value,
                    // Persian yes/no rather than a boolean, because the token sits
                    // in prose in someone's Word template and `1` there would be
                    // read as an attendance count.
                    'present' => $attendee->isPresent
                        ? __('board_meeting.print.yes')
                        : __('board_meeting.print.no'),
                ],
                $meeting->attendees,
            ),

            'agenda' => array_map(
                fn (AgendaItem $item): array => [
                    'position' => ValuePresenter::persianDigits((string) $item->position),
                    'title' => $item->title,
                ],
                $meeting->agenda,
            ),

            'tag' => $meeting->tags
                ->map(fn ($tag): array => ['name' => $tag->name])
                ->values()
                ->all(),

            // A trashed usage is a document the meeting no longer carries, so it
            // must not appear in a template that prints one — the same rule the
            // sheet's own attachment sections apply.
            'approval' => $meeting->documentUsages
                ->filter(fn (DocumentUsage $usage): bool => ! $usage->trashed())
                ->map(fn (DocumentUsage $usage): array => [
                    'registry_number' => ValuePresenter::persianDigits(
                        (string) ($usage->metadata['registry_number'] ?? ''),
                    ),
                    'document_title' => (string) ($usage->metadata['title'] ?? ''),
                ])
                ->values()
                ->all(),

            'attachment' => $meeting->documentUsages
                ->filter(fn (DocumentUsage $usage): bool => ! $usage->trashed())
                ->map(fn (DocumentUsage $usage): array => [
                    'file_name' => (string) ($usage->document?->original_name ?? ''),
                    'size' => $usage->document?->size !== null
                        ? ValuePresenter::persianDigits(
                            number_format($usage->document->size / 1024, 1).' KB',
                        )
                        : '',
                    'mime' => (string) ($usage->document?->mime_type ?? ''),
                    'uploaded_at' => $usage->created_at?->format('Y/m/d H:i') ?? '',
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * A summary list as one line of prose.
     *
     * Joined rather than bulleted because a Word template decides the
     * punctuation around the token; giving it a pre-formatted string with
     * separators bakes this decision in, and the template author who wanted a
     * comma-separated run rather than a Persian separator has no way to get it.
     *
     * @param  array<string, mixed>  $summary
     */
    private function joinSummaryList(array $summary, string $key): string
    {
        $value = $summary[$key] ?? null;

        if (! is_array($value)) {
            return (string) ($value ?? '');
        }

        return implode('، ', array_map(
            // Tags arrive as `{name, slug}` objects and everything else as
            // strings; a slug is not something a printed sheet needs.
            fn (mixed $entry): string => is_array($entry)
                ? (string) ($entry['name'] ?? '')
                : (string) $entry,
            array_values($value),
        ));
    }
}
