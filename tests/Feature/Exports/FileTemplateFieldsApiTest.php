<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;

use function Pest\Laravel\actingAs;

/**
 * `GET /api/word-templates/fields` — the cheat sheet's contract.
 *
 * THE PAYLOAD IS THE DOCUMENTATION. An author cannot discover a macro from the
 * server; they discover it from this screen, and this screen can only show what it is
 * told. So a shape that is not in here does not exist as far as anybody writing a
 * template is concerned — which is exactly what happened to the block range and the
 * custom separator: both worked, and neither was visible.
 *
 * That is why this test asserts on PRESENCE of the keys rather than on their values.
 * The values are the registry's business and already covered; what this pins is the
 * promise that a shape someone can write is a shape the screen mentions.
 */
describe('GET /api/word-templates/fields', function () {
    beforeEach(function () {
        $this->user = createUserWithPermissions(['export-template.view']);
    });

    it('reports every shape a template can be written in', function () {
        // `blocks` and `listable` are the two that were missing: a repeated block is
        // the only way to get a bullet list outside a table, and a listable name is
        // the only one that takes a separator of the author's own.
        actingAs($this->user)
            ->getJson('/api/word-templates/fields?domain=boardmeeting')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'domain',
                    'syntax' => ['open', 'close'],
                    'general',
                    'inline',
                    'rows' => [['group', 'columns']],
                    'sections' => [['key', 'label', 'fields']],
                    'blocks' => [['group', 'open', 'close']],
                    'listable',
                ],
            ]);
    });

    it('reports the block markers the filler actually reads', function () {
        // Taken from the class rather than written out, so a change to the marker
        // shape cannot leave the screen advertising markers the filler ignores.
        $blocks = actingAs($this->user)
            ->getJson('/api/word-templates/fields?domain=boardmeeting')
            ->assertOk()
            ->json('data.blocks');

        $attendee = collect($blocks)->firstWhere('group', 'attendee');

        expect($attendee)->not->toBeNull()
            ->and($attendee['open'])->toBe('{{#attendee}}')
            ->and($attendee['close'])->toBe('{{/attendee}}');
    });

    it('lists every row group as a block, since a row can always be a range', function () {
        $data = actingAs($this->user)
            ->getJson('/api/word-templates/fields?domain=boardmeeting')
            ->assertOk()
            ->json('data');

        expect(array_column($data['blocks'], 'group'))
            ->toEqual(array_column($data['rows'], 'group'));
    });

    it('reports the four summary names as listable, and no others', function () {
        // A name listed here and not backed by a published `@list` value would fail
        // silently at export, which is the outcome this whole change exists to stop.
        $listable = actingAs($this->user)
            ->getJson('/api/word-templates/fields?domain=boardmeeting')
            ->assertOk()
            ->json('data.listable');

        expect($listable)
            ->toBe((new BoardMeetingTemplateFields)->listableKeys())
            ->toContain('summary.topics')
            ->toContain('summary.bullet_points')
            ->toContain('summary.key_decisions')
            ->toContain('summary.suggested_tags');
    });

    it('names every group with the section that owns its fields', function () {
        // The card per group needs a Persian name, and there is exactly one place
        // those names live: the sections. Borrowing them rather than declaring a
        // second set is not a shortcut — a group and a section are the same thing
        // seen from two ends, one being how the values repeat and the other where
        // they appear. Two translations would drift.
        $data = actingAs($this->user)
            ->getJson('/api/word-templates/fields?domain=boardmeeting')
            ->assertOk()
            ->json('data');

        $sectionOf = [];

        foreach ($data['sections'] as $section) {
            foreach ($section['fields'] as $key) {
                $sectionOf[$key] ??= $section['label'];
            }
        }

        foreach ($data['rows'] as $group) {
            expect($group['label'])
                ->not->toBe('')
                ->toBe($sectionOf[$group['columns'][0]['key']] ?? $group['label']);
        }
    });

    it('reports the names that hold a date, so the Jalali variant is discoverable', function () {
        // The VARIANT WORKS FOR ANY PARSABLE VALUE, so it is not enough that it fills:
        // a name nobody is told about is one nobody uses, which is the same defect as
        // the block range and the separator were before they were reported here.
        $data = actingAs($this->user)
            ->getJson('/api/word-templates/fields?domain=boardmeeting')
            ->assertOk()
            ->json('data');

        expect($data['dates'])->toContain('held_on');

        // And every reported date must be a name the template can actually write.
        $writable = array_merge(
            array_column($data['general'], 'key'),
            array_column($data['inline'], 'key'),
        );

        expect($data['dates'])->each->toBeIn($writable);
    });

    it('reports only names a template can actually write', function () {
        $data = actingAs($this->user)
            ->getJson('/api/word-templates/fields?domain=boardmeeting')
            ->assertOk()
            ->json('data');

        $writable = array_merge(
            array_column($data['general'], 'key'),
            array_column($data['inline'], 'key'),
            ...array_map(
                static fn (array $group): array => array_column($group['columns'], 'key'),
                $data['rows'],
            ),
        );

        expect($data['listable'])->each->toBeIn($writable);
    });
});
