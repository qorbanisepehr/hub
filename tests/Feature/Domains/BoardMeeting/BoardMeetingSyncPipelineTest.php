<?php

use App\Contracts\BoardMeeting\RegistrySync;
use App\Contracts\External\FarzinGateway;
use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Events\BoardMeetingSynced;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Approval;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\Ingest\Contracts\Normalizer;
use App\Domains\Ingest\IngestRegistry;
use App\Domains\Ingest\Jobs\SyncIngestRecordsJob;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Integrations\Farzin\FakeFarzinSource;
use App\Integrations\Farzin\FarzinVocabulary;
use Database\Factories\BoardMeeting\BoardMeetingFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * The whole Farzin path: source rows → generic records → registry
 * snapshots → meetings.
 */
beforeEach(function () {
    config()->set('integrations.farzin.driver', 'fake');

    Storage::fake('local');
});

it('syncs source rows into generic records in one step', function () {
    $sync = app(RegistrySync::class);

    $first = $sync->run(false);
    $second = $sync->run(false);

    expect($first['records'])->toBe(['synced' => 3, 'failed' => 0])
        ->and($second['records'])->toBe(['synced' => 3, 'failed' => 0])
        ->and(IntegrationRecord::count())->toBe(3);
});

it('builds the registry entry with minutes, agenda, and attendance', function () {
    app(RegistrySync::class)->run(false);

    $meeting = BoardMeeting::query()->sole();

    expect($meeting->held_on->toDateString())->toBe('2025-09-06')
        ->and($meeting->held_on_jalali)->toBe('1404-06-15')
        ->and($meeting->origin)->toBe('farzin')
        // The form-letter wrapper and the list numbering are gone: what
        // is left is the letter's own words.
        ->and($meeting->minutes)->toBe('تصویب صورتهای مالی')
        ->and($meeting->agenda)->toHaveCount(1)
        ->and($meeting->agenda[0])->toBeInstanceOf(AgendaItem::class)
        ->and($meeting->agenda[0]->title)->toBe('تصویب صورتهای مالی')
        ->and($meeting->attendees)->toHaveCount(1)
        ->and($meeting->attendees[0])->toBeInstanceOf(Attendee::class)
        ->and($meeting->attendees[0]->role)->toBe(AttendeeRole::Member)
        ->and($meeting->provenance['letter_ids'])->toHaveCount(1)
        ->and($meeting->provenance['session_ids'])->toHaveCount(1);
});

it('keys the meeting by the invitation date, not the letter date', function () {
    // Regression (dev-data review 2026-09-27): a letter SENT on
    // 2025-09-01 (Jalali 1404-06-10) inviting a meeting HELD on
    // 1404-06-15 must file the meeting under 1404-06-15.
    $letter = FakeFarzinSource::SAMPLE_LETTERS[0];
    $letter['EntityCode'] = 301;
    $letter['EntityNumber'] = 453;
    $letter['date'] = '2025-09-01';

    $this->app->instance(FarzinGateway::class, new FakeFarzinSource([$letter], []));

    app(RegistrySync::class)->run(false);

    expect(BoardMeeting::query()->sole()->held_on_jalali)->toBe('1404-06-15')
        ->and(BoardMeeting::query()->sole()->held_on->toDateString())->toBe('2025-09-06');
});

it('is idempotent: re-running the sync refreshes without duplicating', function () {
    $sync = app(RegistrySync::class);

    $first = $sync->run(false);
    $second = $sync->run(false);

    expect($first['meetings'])->toBe(['created' => 1, 'refreshed' => 0, 'locked' => 0])
        ->and($second['meetings'])->toBe(['created' => 0, 'refreshed' => 1, 'locked' => 0])
        ->and(BoardMeeting::count())->toBe(1);
});

it('records one aggregate audit event per import with the outcome counts', function () {
    Event::fake([BoardMeetingSynced::class]);

    app(RegistrySync::class)->run(false);

    Event::assertDispatched(BoardMeetingSynced::class, function (BoardMeetingSynced $event) {
        return $event->metadata() === ['created' => 1, 'refreshed' => 0, 'locked' => 0];
    });
});

it('stays silent when an import has nothing to sync', function () {
    Event::fake([BoardMeetingSynced::class]);

    $this->app->instance(FarzinGateway::class, new FakeFarzinSource([], []));

    app(RegistrySync::class)->run(false);

    Event::assertNotDispatched(BoardMeetingSynced::class);
    expect(BoardMeeting::count())->toBe(0);
});

it('never rewrites approvals, so a sync cannot drop the imported numbers', function () {
    // Regression (ADR-011 decision 9). Approvals used to be projected from
    // the TCK archive folder's filenames on every sync. That folder is not
    // a Farzin source, so a production sync read it empty — and the
    // wholesale replace took all 269 registry numbers and their documents
    // with it. The archive is gone; the import owns the list now.
    $meeting = BoardMeetingFactory::new()
        ->withApprovals(new Approval('1111/ص/1404', 'صورت‌های مالی', 7))
        ->create(['held_on_jalali' => '1404-06-15', 'sequence' => 1]);

    app(RegistrySync::class)->run(false);

    $synced = $meeting->fresh();

    expect($synced->approvals)->toHaveCount(1)
        ->and($synced->approvals[0]->number)->toBe('1111/ص/1404')
        ->and($synced->approvals[0]->title)->toBe('صورت‌های مالی')
        ->and($synced->approvals[0]->documentId)->toBe(7);
});

it('skips a source row without its identity and syncs the rest', function () {
    $broken = FakeFarzinSource::SAMPLE_LETTERS[0];
    unset($broken['EntityCode']);

    $good = FakeFarzinSource::SAMPLE_LETTERS[0];
    $good['EntityCode'] = 999;

    $rows = FarzinVocabulary::landInstructions(new FakeFarzinSource([$broken, $good], []), null);
    $outcome = app(SyncIngestRecordsJob::class)->handle(FarzinVocabulary::SOURCE, $rows);

    expect($outcome)->toBe(['synced' => 1, 'failed' => 0])
        ->and(IntegrationRecord::where('external_id', '999')->exists())->toBeTrue();
});

it('reports a row as failed when its normalizer throws', function () {
    // A normalizer that rejects everything — stands in for a source
    // glitch or a newly-appeared unmapped field.
    app(IngestRegistry::class)->register('test', 'broken', new class implements Normalizer
    {
        public function entityType(): string
        {
            return 'broken-entity';
        }

        public function normalize(array $payload): array
        {
            throw new RuntimeException('nope');
        }
    });

    $outcome = app(SyncIngestRecordsJob::class)->handle('test', [
        [
            'source_table' => 'broken',
            'external_id' => '1',
            'payload' => ['x' => 1],
        ],
    ]);

    expect($outcome)->toBe(['synced' => 0, 'failed' => 1])
        ->and(IntegrationRecord::where('entity_type', 'broken-entity')->exists())->toBeFalse();
});
