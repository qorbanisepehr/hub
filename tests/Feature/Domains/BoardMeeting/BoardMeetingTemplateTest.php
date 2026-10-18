<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\Settings\Exceptions\InvalidSetting;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Domains\Tagging\Services\TagService;
use App\Support\Exports\Templates\ExportTemplate;
use App\Support\Exports\Templates\FileTemplate;
use App\Support\Exports\Templates\TemplateResolver;

/**
 * Which sections a printed meeting contains, and who says so.
 *
 * The rules worth protecting are the order of authority and the asymmetry
 * around malformed names: an entity beats its domain, a domain beats the
 * built-in list, and a template naming a section this version has never heard
 * of still renders everything else. That last one is what lets a rollback print
 * something slightly wrong instead of nothing.
 */
describe('board meeting export templates', function () {
    beforeEach(function () {
        $this->repo = app(SystemSettingsRepositoryInterface::class);
        $this->resolver = app(TemplateResolver::class);
    });

    /**
     * A meeting with enough content for EVERY section to be non-empty.
     *
     * `document()` drops empty sections, which is right for a reader and wrong
     * for a test that wants to know which sections a template selected: a
     * section that is absent because it was removed and a section that is absent
     * because the meeting has nothing to put in it look identical. Populating
     * all of them removes that ambiguity.
     */
    $fullMeeting = function (array $attributes = []): BoardMeeting {
        $meeting = BoardMeeting::factory()
            ->withAttendees(
                new Attendee('دبیر جلسه', AttendeeRole::Chair, true),
                new Attendee('عضو هیئت مدیره', AttendeeRole::Member, false),
            )
            ->withAgenda(
                new AgendaItem(1, 'بررسی بودجه'),
                new AgendaItem(2, 'گزارش مالی'),
            )
            ->withApprovalDocuments(1)
            ->create($attributes);

        app(TagService::class)->syncTags($meeting, ['راهبری']);

        return $meeting->fresh();
    };

    /** The section headings a rendered sheet actually carries, in order. */
    $renderedHeadings = function (BoardMeeting $meeting): array {
        $spec = app(BoardMeetingSheetExport::class)->document($meeting);

        return array_map(fn ($section): string => $section->heading, $spec->sections);
    };

    // Resolved from the container rather than captured off `$this`: a closure
    // bound to a test case is not `$this` when invoked from another one, and
    // reading the repository the same way everywhere avoids the trap entirely.
    $store = function (string $domain, ?string $entity, array $sections): void {
        app(SystemSettingsRepositoryInterface::class)
            ->put(ExportTemplate::make($domain, $entity, $sections));
    };

    it('prints every section when nothing is stored', function () use ($renderedHeadings, $fullMeeting) {
        // Counted rather than compared to `sectionNames()`: a section's heading
        // is its translated label, so the rendered spec carries names while
        // `sectionNames()` carries keys. Eight rendered sections for eight
        // declared ones, all distinct, is the claim worth making.
        $headings = $renderedHeadings($fullMeeting());

        expect($headings)->toHaveCount(count(app(BoardMeetingSheetExport::class)->sectionNames()))
            ->and(array_unique($headings))->toHaveCount(count($headings));
    });

    it('prints only what the domain template names, in the order it names them', function () use ($renderedHeadings, $fullMeeting, $store) {
        // Captured BEFORE the template exists, so this is the full render: one
        // heading per declared section, in the default order. It indexes into
        // `sectionNames()`, so a template naming `summary` first must print what
        // a default render puts fifth.
        $every = $renderedHeadings($fullMeeting());

        $store(BoardMeetingSheetExport::TEMPLATE_DOMAIN, null, ['summary', 'identity']);

        expect($renderedHeadings($fullMeeting()))
            ->toBe([$every[4], $every[0]])
            ->and($every)->toHaveCount(count(app(BoardMeetingSheetExport::class)->sectionNames()));
    });

    it('lets one meeting override its domain', function () use ($renderedHeadings, $fullMeeting, $store) {
        $domain = BoardMeetingSheetExport::TEMPLATE_DOMAIN;

        $store($domain, null, ['identity']);
        $store($domain, 'session/7', ['minutes', 'attendees']);

        $overridden = $fullMeeting(['id' => 7]);
        $untouched = $fullMeeting();

        expect($renderedHeadings($overridden))->toHaveCount(2)
            ->and($renderedHeadings($untouched))->toHaveCount(1);
    });

    it('falls back to the domain template when an override is dropped', function () use ($renderedHeadings, $fullMeeting, $store) {
        $domain = BoardMeetingSheetExport::TEMPLATE_DOMAIN;

        $store($domain, null, ['identity']);
        $store($domain, 'session/7', ['minutes']);

        // Through the resolver, so the test spells the key the same way the
        // export and the delete do. Hand-building `session/7` here would delete
        // nothing and the test would pass or fail for the wrong reason.
        app(TemplateResolver::class)->forget($domain, 'session/7');

        expect($renderedHeadings($fullMeeting(['id' => 7])))->toHaveCount(1);
    });

    it('renders the rest of a template that names a section it does not have', function () use ($renderedHeadings, $fullMeeting, $store) {
        // A template written by a newer deploy has to survive a rollback.
        // Refusing the read would break export entirely; dropping one name costs
        // one section.
        $store(BoardMeetingSheetExport::TEMPLATE_DOMAIN, null, ['identity', 'a_section_from_the_future', 'minutes']);

        expect($renderedHeadings($fullMeeting()))->toHaveCount(2);
    });

    it('never prints a section twice, even if a template lists it twice', function () use ($renderedHeadings, $fullMeeting, $store) {
        $store(BoardMeetingSheetExport::TEMPLATE_DOMAIN, null, ['identity', 'identity', 'minutes']);

        expect($renderedHeadings($fullMeeting()))->toHaveCount(2);
    });

    it('does not build a section a template removed', function () use ($fullMeeting, $store) {
        $store(BoardMeetingSheetExport::TEMPLATE_DOMAIN, null, ['identity']);

        $sections = app(BoardMeetingSheetExport::class)->document($fullMeeting())->sections;

        // The meeting really does have three attendees; a template that hides
        // the table must not merely hide the output, it must not assemble it.
        expect($sections)->toHaveCount(1)
            ->and($sections[0]->tables)->toBeEmpty();
    });

    it('refuses a template whose sections are not a list of names', function () {
        // The stored form is strict about shape: a `sections` that is an object
        // would be read back as an unordered set, and section order is the whole
        // point of a template.
        $this->expectException(InvalidSetting::class);

        ExportTemplate::fromStored('sections/boardmeeting', ['sections' => ['a' => 'identity']], null);
    });

    it('refuses a row stored under one key claiming to be another domain', function () {
        // The key is what every lookup is written against, so a payload
        // disagreeing with it would apply a template to the wrong domain.
        $this->expectException(InvalidSetting::class);

        ExportTemplate::fromStored('sections/boardmeeting', ['domain' => 'cv', 'sections' => []], null);
    });

    it('namespaces an entity key so one record cannot address another', function () {
        // The guarantee is about the KEY, not about the parser: `split()` cannot
        // know that a bare `42` is a meeting id rather than a domain called 42,
        // and it deliberately does not try — it checks shape only, so adding a
        // domain never means editing the kernel. What must hold is that a
        // meeting's override is addressed as `session/{id}`, so it can never
        // collide with a CV numbered 42.
        expect(ExportTemplate::make('boardmeeting', 'session/42', ['identity'])->key())
            ->toBe('sections/boardmeeting/session/42')
            ->and(ExportTemplate::make('boardmeeting', null, ['identity'])->key())
            ->toBe('sections/boardmeeting');

        // And the shape checks that keep a malformed key out of the store.
        expect(ExportTemplate::split('sections/boardmeeting/session/42'))->toBe(['boardmeeting', 'session/42'])
            ->and(ExportTemplate::split('sections/boardmeeting'))->toBe(['boardmeeting', null])
            ->and(ExportTemplate::split('sections/boardmeeting/'))->toBeNull()
            ->and(ExportTemplate::split('sections//session/42'))->toBeNull()
            ->and(ExportTemplate::split('sections/board meeting/42'))->toBeNull();
    });

    it('refuses a key with no namespace at all', function () {
        // A bare `boardmeeting` is exactly what the rename moved away from. If
        // `handles()` still accepted one, nothing would claim it and a stored
        // template would silently stop applying — a quiet revert to the
        // built-in section list that nobody would notice.
        expect(ExportTemplate::handles('boardmeeting'))->toBeFalse()
            ->and(ExportTemplate::handles('boardmeeting/session/42'))->toBeFalse();
    });

    it('shares the group with Word templates without ever claiming their keys', function () {
        // `handles()` is how the store decides which kind of setting owns a key,
        // so the two classes overlapping here would make a listing return the
        // other kind's rows and a delete match nothing.
        $wordKeys = [
            FileTemplate::make('docx', 'boardmeeting', 'official', null, 'نامهٔ رسمی', 10, 'application/docx', true)->key(),
            FileTemplate::make('docx', 'boardmeeting', 'official', 'session/42', 'نامهٔ رسمی', 10, 'application/docx', true)->key(),
        ];

        foreach ($wordKeys as $key) {
            expect(FileTemplate::handles($key))->toBeTrue()
                ->and(ExportTemplate::handles($key))->toBeFalse();
        }

        $sectionKey = ExportTemplate::make('boardmeeting', null, ['identity'])->key();

        expect(ExportTemplate::handles($sectionKey))->toBeTrue()
            ->and(FileTemplate::handles($sectionKey))->toBeFalse();
    });
});

describe('board meeting template endpoints', function () {
    beforeEach(function () {
        $this->repo = app(SystemSettingsRepositoryInterface::class);
    });

    it('names every section a template may use, with its label', function () {
        // Keys alone would force the client to keep its own copy of the domain's
        // vocabulary, and the fallback for a name it has not heard of would be a
        // raw key shown where a person expects a word.
        $available = $this->actingAs(createUserWithPermissions(['export-template.view']))
            ->getJson('/api/board-meetings/templates')
            ->assertOk()
            ->json('data.available');

        expect($available)->toBe(app(BoardMeetingSheetExport::class)->sectionDefinitions())
            ->and(collect($available)->pluck('key')->all())
            ->toBe(app(BoardMeetingSheetExport::class)->sectionNames());

        foreach ($available as $section) {
            expect($section['label'])->not->toBe($section['key']);
        }

        // Nothing stored yet, so `effective` is the built-in list. Asserting it
        // here is what catches a default of the WRONG SHAPE: the resolver drops
        // names it does not recognise rather than failing, so passing the
        // definitions array where keys were expected returns an empty document
        // and nothing else complains.
        expect(
            $this->actingAs(createUserWithPermissions(['export-template.view']))
                ->getJson('/api/board-meetings/templates')
                ->json('data.effective'),
        )->toBe(collect($available)->pluck('key')->all());
    });

    it('stores the domain template and reports it as effective', function () {
        $this->actingAs(createUserWithPermissions(['export-template.manage']))
            ->putJson('/api/board-meetings/templates', ['sections' => ['summary', 'identity']])
            ->assertOk();

        $this->actingAs(createUserWithPermissions(['export-template.view']))
            ->getJson('/api/board-meetings/templates')
            ->assertOk()
            ->assertJsonPath('data.stored', ['summary', 'identity'])
            ->assertJsonPath('data.effective', ['summary', 'identity']);
    });

    it('names the legal sections when one is not one', function () {
        // A typo is worth a 422 that lists the alternatives; a typo nobody is
        // told about is a section that silently never prints.
        $this->actingAs(createUserWithPermissions(['export-template.manage']))
            ->putJson('/api/board-meetings/templates', ['sections' => ['agenda_item']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sections.0');
    });

    it('refuses a template that lists a section twice', function () {
        $this->actingAs(createUserWithPermissions(['export-template.manage']))
            ->putJson('/api/board-meetings/templates', ['sections' => ['identity', 'identity']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sections.1');
    });

    it('refuses a template with no sections at all', function () {
        $this->actingAs(createUserWithPermissions(['export-template.manage']))
            ->putJson('/api/board-meetings/templates', ['sections' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sections');
    });

    it('shows what one meeting will actually print', function () {
        $meeting = BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['export-template.manage']))
            ->putJson("/api/board-meetings/templates/{$meeting->getKey()}", ['sections' => ['minutes']])
            ->assertOk();

        $this->actingAs(createUserWithPermissions(['export-template.view']))
            ->getJson("/api/board-meetings/templates/{$meeting->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.entity', 'session/'.$meeting->getKey())
            ->assertJsonPath('data.stored', ['minutes'])
            // The domain default is not stored, so `effective` proves inheritance
            // rather than restating `stored`.
            ->assertJsonPath('data.effective', ['minutes']);
    });

    it('lists overrides without repeating the domain template as one of them', function () {
        $meeting = BoardMeeting::factory()->create();

        // Both permissions, because this test both writes and reads back. A user
        // holding only `manage` would be refused the read for the right reason
        // and the test would prove nothing about listing.
        $user = createUserWithPermissions(['export-template.manage', 'export-template.view']);

        $this->actingAs($user)
            ->putJson('/api/board-meetings/templates', ['sections' => ['identity']]);

        $this->actingAs($user)
            ->putJson("/api/board-meetings/templates/{$meeting->getKey()}", ['sections' => ['minutes']]);

        $this->actingAs($user)
            ->getJson('/api/board-meetings/templates')
            ->assertOk()
            ->assertJsonCount(1, 'data.overrides')
            ->assertJsonPath('data.overrides.0.entity', 'session/'.$meeting->getKey());
    });

    it('drops an override and hands the meeting back to the domain', function () {
        $meeting = BoardMeeting::factory()->create();

        $user = createUserWithPermissions(['export-template.manage', 'export-template.view']);

        $this->actingAs($user)
            ->putJson('/api/board-meetings/templates', ['sections' => ['identity']]);

        $this->actingAs($user)
            ->putJson("/api/board-meetings/templates/{$meeting->getKey()}", ['sections' => ['minutes']])
            ->assertOk();

        $this->actingAs($user)
            ->deleteJson("/api/board-meetings/templates/{$meeting->getKey()}")
            ->assertOk();

        $this->actingAs($user)
            ->getJson("/api/board-meetings/templates/{$meeting->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.stored', null)
            ->assertJsonPath('data.effective', ['identity']);
    });

    it('keeps reading templates from being a write', function () {
        // One user, holding only `view`, for every mutating route. Both need
        // `manage`: deleting an override is a write like any other, because it
        // changes what the meeting prints by handing the decision back to the
        // domain's template. It is not a lesser read.
        $reader = createUserWithPermissions(['export-template.view']);

        $this->actingAs($reader)
            ->putJson('/api/board-meetings/templates', ['sections' => ['identity']])
            ->assertForbidden();

        $this->actingAs($reader)
            ->deleteJson('/api/board-meetings/templates/1')
            ->assertForbidden();
    });

    it('lets a manager delete, because clearing an override is a write', function () {
        // The counterpart to the test above: the point of splitting view from
        // manage is that `manage` really does carry the destructive half.
        $this->actingAs(createUserWithPermissions(['export-template.manage']))
            ->deleteJson('/api/board-meetings/templates/1')
            ->assertOk();
    });
});
