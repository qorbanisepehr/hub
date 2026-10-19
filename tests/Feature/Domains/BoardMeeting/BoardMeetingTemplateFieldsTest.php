<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Tagging\Services\TagService;
use App\Support\Exports\Templates\TemplateField;
use App\Support\Exports\Templates\TemplateFieldShape;

/**
 * The names a Word template may address.
 *
 * Two things are worth protecting here and neither is the values themselves.
 * The first is that the LIST is coherent — every row field's key derivable from
 * its group, no duplicate keys, every key actually resolvable — because a
 * cheat sheet that lists a name the filler cannot resolve is worse than no cheat
 * sheet. The second is that a trashed attachment disappears from a template as
 * well as from the sheet, which is the rule a template could quietly undo.
 */
describe('board meeting template fields', function () {
    beforeEach(function () {
        $this->registry = new BoardMeetingTemplateFields;
    });

    it('names every field a template may address, under the same domain', function () {
        expect($this->registry->domain())->toBe(BoardMeetingSheetExport::TEMPLATE_DOMAIN)
            ->and($this->registry->fields())->not->toBeEmpty();
    });

    it('gives every field a key that is usable as a placeholder', function () {
        // The key IS the contract with whoever writes the template, so a key
        // containing a space or a bracket would be unusable in a Word document
        // even though it survives this codebase perfectly well.
        foreach ($this->registry->fields() as $field) {
            expect($field->key)->toMatch('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)?$/');
        }
    });

    it('never lists the same key twice', function () {
        $keys = array_map(fn (TemplateField $field): string => $field->key, $this->registry->fields());

        expect(array_unique($keys))->toHaveCount(count($keys));
    });

    it('resolves every field it lists', function () {
        $meeting = BoardMeeting::factory()->create();
        $values = $this->registry->values($meeting);

        foreach ($this->registry->fields() as $field) {
            if ($field->isRow()) {
                // A row field's value lives under its GROUP, not under its key.
                expect($values)->toHaveKey($field->group);

                continue;
            }

            expect($values)->toHaveKey($field->key);
        }
    });

    it('derives a row column from its group and key', function () {
        // Enforced at construction rather than assumed, because a key that did
        // not say `group.column` would have to be guessed at render time.
        $field = TemplateField::row('attendee.name', 'نام', 'attendee');

        expect($field->column())->toBe('name')
            ->and($field->group)->toBe('attendee')
            ->and($field->shape)->toBe(TemplateFieldShape::Row);
    });

    it('refuses a row field whose key does not name its group', function () {
        $this->expectException(InvalidArgumentException::class);

        TemplateField::row('name', 'نام', 'attendee');
    });

    it('refuses a row field with no group', function () {
        $this->expectException(InvalidArgumentException::class);

        TemplateField::row('attendee.name', 'نام', '  ');
    });

    it('gives every column of a group the same length', function () {
        // `cloneRow` is handed one array per column and clones as many times as
        // it is given; a column one entry short produces rows padded with
        // nothing, which reads as a blank attendance entry.
        $meeting = BoardMeeting::factory()
            ->withAttendees(
                new Attendee('الف', AttendeeRole::Chair, true),
                new Attendee('ب', AttendeeRole::Member, false),
                new Attendee('ج', AttendeeRole::Observer, true),
            )
            ->withAgenda(
                new AgendaItem(1, 'اول'),
                new AgendaItem(2, 'دوم'),
            )
            ->create();

        $values = $this->registry->values($meeting);

        // Entries, not columns: a group resolves to a list of rows, and every
        // row carries every column. Counting the entries is therefore counting
        // the rows that will be cloned, which is the thing that has to be right.
        expect($values['attendee'])->toHaveCount(3)
            ->and($values['agenda'])->toHaveCount(2)
            ->and(array_keys($values['attendee'][0]))->toBe(['name', 'role', 'present'])
            ->and(array_keys($values['agenda'][0]))->toBe(['position', 'title']);
    });

    it('says present or absent in words, not as a boolean', function () {
        // The token sits in prose inside somebody's own Word template; a `1`
        // there reads as an attendance count rather than as "yes".
        $meeting = BoardMeeting::factory()
            ->withAttendees(
                new Attendee('الف', AttendeeRole::Chair, true),
                new Attendee('ب', AttendeeRole::Member, false),
            )
            ->create();

        $present = array_column($this->registry->values($meeting)['attendee'], 'present');

        expect($present)->toBe([
            __('board_meeting.print.yes'),
            __('board_meeting.print.no'),
        ]);
    });

    it('names a role in persian, not with the enum value', function () {
        // The same argument as `present`, one level up. A template puts this token in
        // prose inside somebody's own document, and `chair` sitting in the middle of
        // a Persian sentence is a value that leaked out of the database into a place
        // a person reads it — which is also what the JSON export was carrying.
        $meeting = BoardMeeting::factory()
            ->withAttendees(
                new Attendee('الف', AttendeeRole::Chair, true),
                new Attendee('ب', AttendeeRole::Observer, false),
            )
            ->create();

        $roles = array_column($this->registry->values($meeting)['attendee'], 'role');

        expect($roles)->toBe([
            __('board_meeting.print.columns.roles.chair'),
            __('board_meeting.print.columns.roles.observer'),
        ])
            // Named rather than asserted loosely: the point is that the enum's own
            // value is NOT what a reader gets.
            ->and($roles[0])->not->toBe(AttendeeRole::Chair->value);
    });

    it('joins a short summary list into prose instead of giving it rows', function () {
        $meeting = BoardMeeting::factory()->create([
            'summary' => [
                'status' => MeetingSummary::STATUS_OK,
                'topics' => ['بودجه', 'گزارش'],
                'suggested_tags' => [
                    ['name' => 'مالی', 'slug' => 'mali'],
                    ['name' => 'راهبری', 'slug' => 'rahbari'],
                ],
            ],
        ]);

        $values = $this->registry->values($meeting);

        expect($values['summary.topics'])->toBe('بودجه، گزارش')
            // A tag entry is `{name, slug}`; the slug is not something a printed
            // sheet needs and must not leak into one.
            ->and($values['summary.suggested_tags'])->toBe('مالی، راهبری');
    });

    it('resolves a tag group, not just a tag list', function () {
        $meeting = BoardMeeting::factory()->create();

        app(TagService::class)->syncTags($meeting->fresh(), ['راهبری']);

        $values = $this->registry->values($meeting);

        expect($values['tag'])->toHaveCount(1)
            ->and($values['tag'][0])->toHaveKey('name');
    });

    it('leaves a trashed attachment out, as the sheet already does', function () {
        $meeting = BoardMeeting::factory()->withApprovalDocuments(2)->create();

        $usage = $meeting->documentUsages->first();
        $usage->delete();

        $values = $this->registry->values($meeting->fresh());

        // A template must not be a way to print a document the meeting has
        // already disowned.
        expect($values['attachment'])->toHaveCount(1)
            ->and($values['approval'])->toHaveCount(1);
    });

    it('resolves an empty group rather than a blank row', function () {
        $values = $this->registry->values(BoardMeeting::factory()->create());

        expect($values['attendee'])->toBe([])
            ->and(TemplateFieldShape::Row->accepts([]))->toBeFalse();
    });

    it('treats an empty inline value as nothing to print', function () {
        expect(TemplateFieldShape::Inline->accepts(''))->toBeFalse()
            ->and(TemplateFieldShape::Inline->accepts(null))->toBeFalse()
            ->and(TemplateFieldShape::Inline->accepts('متن'))->toBeTrue();
    });
});
