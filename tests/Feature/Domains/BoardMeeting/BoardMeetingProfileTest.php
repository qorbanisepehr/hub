<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingProfile;
use App\Domains\BoardMeeting\Exports\BoardMeetingSection;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\TemplateField;

/**
 * The one list a board meeting document is made of.
 *
 * These are the tests that could not be written before the profile existed. The
 * section keys lived in the sheet export and the field keys in the field registry, so
 * the two drifted freely: a value could be declared that no section ever printed, a
 * section could exist whose values no template could reach, and nothing anywhere
 * noticed. The profile puts them in one declaration, and these are the checks that
 * keep it one declaration rather than two that happen to agree today.
 */
describe('the board meeting profile', function () {
    it('declares a section for everything the sheet can print', function () {
        // THE DRIFT TEST. The builders are rendering, the profile is vocabulary, and
        // nothing in either type forces them to agree — so this is the only place the
        // fact is checked. A section someone can print but no template can name is
        // invisible in the cheat sheet and in every validation message, and it is
        // exactly the kind of addition that arrives on a Friday.
        $builders = (new ReflectionMethod(BoardMeetingSheetExport::class, 'sectionBuilders'))
            ->invoke(app(BoardMeetingSheetExport::class));

        expect(array_keys($builders))->toBe((new BoardMeetingProfile)->sectionKeys());
    });

    it('gives every value a section, so the cheat sheet can say where it appears', function () {
        // The question that had no answer while the two lists were separate, and the
        // reason a person laying out a template cannot work from a flat list of keys:
        // they are deciding what goes where.
        $profile = new BoardMeetingProfile;

        foreach ($profile->fields() as $field) {
            expect($profile->sectionOf($field->key))
                ->toBeInstanceOf(BoardMeetingSection::class)
                ->and($profile->sectionOf($field->key)->fieldKeys())->toContain($field->key);
        }
    });

    it('gives no value to two sections', function () {
        // One source means one place per key. A key listed under two sections would
        // make "where does this appear" answer with two sections, and the template
        // author gets no way to choose.
        $profile = new BoardMeetingProfile;

        $seen = [];

        foreach ($profile->sections() as $section) {
            foreach ($section->fieldKeys() as $key) {
                expect($seen)->not->toContain($key);

                $seen[] = $key;
            }
        }

        expect($seen)->toHaveCount(count($profile->fields()));
    });

    it('lists the values in the order the sheet prints them', function () {
        // Section order rather than alphabetical, so the cheat sheet and the document
        // agree about what comes first.
        $profile = new BoardMeetingProfile;

        expect(array_slice($profile->fieldKeys(), 0, 2))->toBe(['title', 'held_on'])
            ->and($profile->fieldKeys())->toContain('attendee.role')
            ->and($profile->fieldKeys())->toContain('attachment.uploaded_at');
    });

    it('carries no value the field registry cannot address', function () {
        // The other direction of the drift, and the one with a visible symptom: a
        // value in the profile that the resolver does not produce would fill as an
        // empty string, so a template would show a label and no value, forever.
        $profile = new BoardMeetingProfile;
        $registry = new BoardMeetingTemplateFields;

        $resolved = $registry->values(
            BoardMeeting::factory()->create(),
        );

        foreach ($profile->fields() as $field) {
            $key = $field->isRow()
                ? $field->group
                : $field->key;

            expect($resolved)->toHaveKey($key);
        }
    });

    it('keeps repeating values in groups, and prose inline', function () {
        // The one structural rule a group depends on: a row-shaped value needs a
        // group name, or there is no collection for `cloneRow` to work against. The
        // profile does not weaken it, it just declares in one place now.
        $profile = new BoardMeetingProfile;

        foreach ($profile->fields() as $field) {
            if ($field->isRow()) {
                expect($field->group)->toBeString()->not->toBe('')
                    ->and($field->key)->toStartWith($field->group.'.');
            } else {
                expect($field->group)->toBeNull();
            }
        }
    });

    it('is the field list the registry publishes, not a second one', function () {
        // The merge itself. Two lists that happen to agree today is not one source,
        // and this is the assertion that says so.
        $profile = new BoardMeetingProfile;
        $registry = new BoardMeetingTemplateFields;

        expect(array_map(
            static fn (TemplateField $field): string => $field->key,
            $registry->fields(),
        ))->toBe(array_map(
            static fn (TemplateField $field): string => $field->key,
            $profile->fields(),
        ));
    });

    it('is the section list the sheet advertises to a picker', function () {
        $profile = new BoardMeetingProfile;
        $sheet = app(BoardMeetingSheetExport::class);

        expect($sheet->sectionNames())->toBe($profile->sectionKeys())
            ->and(array_column($sheet->sectionDefinitions(), 'key'))->toBe($profile->sectionKeys());
    });
});
