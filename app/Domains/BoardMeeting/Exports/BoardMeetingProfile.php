<?php

namespace App\Domains\BoardMeeting\Exports;

use App\Support\Exports\Templates\TemplateField;

/**
 * What a board meeting document is made of: its sections, and the values each one
 * carries.
 *
 * ONE LIST, WHICH IS THE WHOLE POINT. A meeting sheet and a meeting template both
 * draw on the same vocabulary, and that vocabulary used to be declared twice — the
 * section keys in {@see BoardMeetingSheetExport} and the field keys in
 * {@see BoardMeetingTemplateFields} — with nothing joining them. So a field could be
 * added that no section ever prints, a section could exist whose values no template
 * could address, and neither mistake was a mistake anybody could see.
 *
 * Merging them means the cheat sheet can answer a question it could not before:
 * not only "which values may I write" but "where does each one appear". A person
 * laying out a template is deciding what goes where, and a list of twenty-two bare
 * keys does not tell them that `attendee.role` lives in the attendees section.
 *
 * A DOMAIN DECLARES IT, and the kernel never guesses. It could not: only this domain
 * knows what a meeting has, and a profile listing domains in the kernel would have to
 * be edited every time a domain gained its first document — the coupling the export
 * kernel was built to avoid.
 *
 * WHAT IS NOT HERE. How a section is BUILT. Rendering is
 * {@see BoardMeetingSheetExport}'s, because building needs the loaded meeting and the
 * section builders already live there; this class says what exists and what a
 * template may put in it, not how the result looks. The two are held together by a
 * test rather than by a shared base class, because a builder that can print a section
 * the profile does not declare is the exact drift worth failing on.
 *
 * Ordering matters throughout and is declared here once. A template that reorders
 * sections reorders the profile's order, and a cheat sheet lists them in the order
 * they will appear on the page.
 */
final class BoardMeetingProfile
{
    /**
     * Every printable section, in the order this sheet has always printed them.
     *
     * @return list<BoardMeetingSection>
     */
    public function sections(): array
    {
        return [
            new BoardMeetingSection(
                key: 'identity',
                label: __('board_meeting.print.sections.identity'),
                fields: [
                    TemplateField::inline('title', __('board_meeting.print.fields.title')),
                    TemplateField::inline('held_on', __('board_meeting.print.fields.held_on')),
                    TemplateField::inline('sequence', __('board_meeting.print.fields.sequence')),
                    TemplateField::inline('origin', __('board_meeting.print.fields.origin')),
                ],
            ),

            new BoardMeetingSection(
                key: 'attendees',
                label: __('board_meeting.print.sections.attendees'),
                fields: [
                    TemplateField::row('attendee.name', __('board_meeting.print.columns.name'), 'attendee'),
                    TemplateField::row('attendee.role', __('board_meeting.print.columns.role'), 'attendee'),
                    TemplateField::row('attendee.present', __('board_meeting.print.columns.present'), 'attendee'),
                ],
            ),

            new BoardMeetingSection(
                key: 'agenda',
                label: __('board_meeting.print.sections.agenda'),
                fields: [
                    TemplateField::row('agenda.position', __('board_meeting.print.columns.position'), 'agenda'),
                    TemplateField::row('agenda.title', __('board_meeting.print.columns.agenda_title'), 'agenda'),
                ],
            ),

            new BoardMeetingSection(
                key: 'minutes',
                // The minutes are prose that happens to be one field, not a group of
                // columns, so they are inline. Their LABEL reuses the section's own
                // wording on purpose: it is the same word on the page and in the
                // cheat sheet, and a second spelling of "minutes" would only ever be
                // a place for the two to drift apart.
                label: __('board_meeting.print.sections.minutes'),
                fields: [
                    TemplateField::inline('minutes', __('board_meeting.print.sections.minutes')),
                ],
            ),

            new BoardMeetingSection(
                key: 'summary',
                label: __('board_meeting.print.sections.summary'),
                fields: [
                    TemplateField::inline('summary.status', __('board_meeting.print.fields.status')),
                    TemplateField::inline('summary.error', __('board_meeting.print.fields.error')),
                    TemplateField::inline('summary.executive_summary', __('board_meeting.print.fields.executive_summary')),
                    TemplateField::inline('summary.topics', __('board_meeting.print.fields.topics')),
                    TemplateField::inline('summary.bullet_points', __('board_meeting.print.fields.bullet_points')),
                    TemplateField::inline('summary.key_decisions', __('board_meeting.print.fields.key_decisions')),
                    TemplateField::inline('summary.suggested_tags', __('board_meeting.print.fields.suggested_tags')),
                ],
            ),

            new BoardMeetingSection(
                key: 'approvals',
                label: __('board_meeting.print.sections.approvals'),
                fields: [
                    TemplateField::row('approval.registry_number', __('board_meeting.print.columns.registry_number'), 'approval'),
                    TemplateField::row('approval.document_title', __('board_meeting.print.columns.document_title'), 'approval'),
                ],
            ),

            new BoardMeetingSection(
                key: 'tags',
                label: __('board_meeting.print.sections.tags'),
                fields: [
                    TemplateField::row('tag.name', __('board_meeting.print.columns.tag'), 'tag'),
                ],
            ),

            new BoardMeetingSection(
                key: 'attachments',
                label: __('board_meeting.print.sections.attachments'),
                fields: [
                    TemplateField::row('attachment.file_name', __('board_meeting.print.columns.file_name'), 'attachment'),
                    TemplateField::row('attachment.size', __('board_meeting.print.columns.size'), 'attachment'),
                    TemplateField::row('attachment.mime', __('board_meeting.print.columns.mime'), 'attachment'),
                    TemplateField::row('attachment.uploaded_at', __('board_meeting.print.columns.uploaded_at'), 'attachment'),
                ],
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public function sectionKeys(): array
    {
        return array_map(
            static fn (BoardMeetingSection $section): string => $section->key,
            $this->sections(),
        );
    }

    /**
     * Every value a template may address, flattened in section order.
     *
     * Section order rather than alphabetical, so the cheat sheet and the document
     * agree on what comes first.
     *
     * @return list<TemplateField>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->sections() as $section) {
            foreach ($section->fields as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Every value's key, in the same order.
     *
     * @return list<string>
     */
    public function fieldKeys(): array
    {
        return array_map(
            static fn (TemplateField $field): string => $field->key,
            $this->fields(),
        );
    }

    /**
     * The section a value belongs to, or null when it belongs to none.
     *
     * The question the cheat sheet asks, and the one that had no answer while the two
     * lists were separate.
     */
    public function sectionOf(string $fieldKey): ?BoardMeetingSection
    {
        foreach ($this->sections() as $section) {
            if (in_array($fieldKey, $section->fieldKeys(), true)) {
                return $section;
            }
        }

        return null;
    }
}
