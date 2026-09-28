<?php

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSyncService;
use App\Domains\BoardMeeting\Services\MeetingNumberService;
use App\Domains\BoardMeeting\Services\MeetingProjection;
use App\Domains\Ingest\Contracts\Normalizer;
use App\Domains\Ingest\IngestRegistry;
use App\Domains\Ingest\Jobs\SyncIngestRecordsJob;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Integrations\Farzin\FakeFarzinSource;
use App\Integrations\Farzin\FarzinGatewayAdapter;
use App\Integrations\Farzin\FarzinVocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Deterministic fake source; storage untouched (no archive docs).
    config()->set('board-meeting.driver', 'fake');
    config()->set('board-meeting.archive_disk', 'local');
    config()->set('board-meeting.archive_path', 'archive/approvals-test-'.uniqid());
});

it('syncs source rows into generic records in one step', function () {
    $service = app(BoardMeetingSyncService::class);

    $first = $service->run(false);
    $second = $service->run(false);

    expect($first['records'])->toBe(['synced' => 3, 'failed' => 0])
        ->and($second['records'])->toBe(['synced' => 3, 'failed' => 0])
        ->and(IntegrationRecord::count())->toBe(3);
});

it('normalizes rows with the source vocabulary intact in original', function () {
    app(BoardMeetingSyncService::class)->run(false);

    $letter = IntegrationRecord::query()
        ->forEntity('letter')->where('external_id', '101')->first();
    $session = IntegrationRecord::query()
        ->forEntity('session')->where('external_id', '201')->first();

    // CDATA unwrapped, Arabic folded, inner HTML kept (TCK parity),
    // Persian digits preserved; meeting fragment extracted into data.
    expect($letter->title)->toBe('<p>دعوت به جلسه هیئت مدیره مورخ ۱۴۰۴/۰۶/۱۵</p>')
        ->and($letter->data['r_name'])->toBe('یوسف احمدی')
        ->and($letter->data['has_meeting'])->toBeTrue()
        ->and($letter->data['meeting_pdate'])->toBe('1404-06-15')
        ->and($letter->pdate)->toBe('1404-06-15')
        ->and($letter->original)->toBeArray()
        ->and($letter->source_table)->toBe('Entity_A4letter')
        ->and($letter->status)->toBe('processed')
        ->and($session->pdate)->toBe('1404-06-15')
        ->and($session->data['sharh'])->toContain('جلسه هیئت مدیره ساعت ۱۰');
});

it('projects meetings from generic records', function () {
    app(BoardMeetingSyncService::class)->run(false);

    $meeting = BoardMeeting::query()->where('pdate', '1404-06-15')->first();

    expect($meeting)->not->toBeNull()
        ->and($meeting->minutes_from_letters)->toBeArray()
        ->and(count($meeting->minutes_from_letters))->toBe(1)
        ->and($meeting->minutes_hash)->toBe(hash('sha256', implode("\n", $meeting->minutes_from_letters)))
        ->and($meeting->minutes_from_heyat_modire)->toBe(['جلسه هیئت مدیره ساعت ۱۰ در محل شرکت تشکیل شد'])
        ->and($meeting->meta['heyat_modire_count'])->toBe(1);
});

it('skips unchanged meetings on re-projection', function () {
    $service = app(BoardMeetingSyncService::class);

    $service->run(false);
    $result = $service->run(false);

    expect($result['meetings'])->toBe(['new' => 0, 'updated' => 0, 'unchanged' => 1]);
});

it('keeps minute body after normalizer collapse (TCK parity)', function () {
    app(BoardMeetingSyncService::class)->run(false);

    $meeting = BoardMeeting::query()->where('pdate', '1404-06-15')->first();
    $minute = $meeting->minutes_from_letters[0];

    // TCK parity: the normalizer collapsed newlines to spaces and kept
    // the CDATA inner markup; body content and numbering remain.
    expect($minute)->toContain('تصویب')
        ->and($minute)->toContain('دستور جلسه:');
});

it('extracts meeting numbers from document filenames (TCK parity)', function () {
    $service = new MeetingNumberService;

    expect($service->fromFilenames(['扫描 1234(1404-06-15).pdf']))->toBe('1234/ص/1404')
        ->and($service->fromFilenames(['file 5678-2(1404-06-15).pdf']))->toBe('2/5678/ص/1404')
        ->and($service->fromFilenames([
            'a 1111(1404-06-15).pdf',
            'b 2222-3(1404-06-15).pdf',
        ]))->toBe('1111/ص/1404-3/2222/ص/1404')
        ->and($service->fromFilenames(['no-match.pdf']))->toBe('')
        ->and($service->fromFilenames(null))->toBe('');
});

it('groups letters by the meeting date from the subject, not the letter date', function () {
    // Regression (dev-data review 2026-09-27): a letter SENT on 1404-06-10
    // inviting to a meeting HELD on 1404-06-15 must produce the meeting
    // keyed by the invitation's meeting_pdate (TCK Meeting pseudo-model
    // groups by meeting_pdate) — never the letter's own pdate.
    $letter = FakeFarzinSource::SAMPLE_LETTERS[0];
    $letter['EntityCode'] = 301;
    $letter['EntityNumber'] = 453;
    $letter['date'] = '2025-09-01'; // Jalali 1404-06-10 ≠ meeting date.

    $gateway = new FarzinGatewayAdapter(new FakeFarzinSource([$letter], []));
    $rows = FarzinVocabulary::landInstructions($gateway, null);

    app(SyncIngestRecordsJob::class)->handle('farzin', $rows);
    app(MeetingProjection::class)->project();

    expect(BoardMeeting::query()->where('pdate', '1404-06-15')->exists())->toBeTrue()
        ->and(BoardMeeting::query()->where('pdate', '1404-06-10')->exists())->toBeFalse();
});

it('skips unmappable rows at the boundary and syncs the rest', function () {
    // A row without its primary key cannot be synced — the adapter skips
    // it instead of killing the whole batch.
    $broken = FakeFarzinSource::SAMPLE_LETTERS[0];
    unset($broken['EntityCode']);

    $good = FakeFarzinSource::SAMPLE_LETTERS[0];
    $good['EntityCode'] = 999;

    $gateway = new FarzinGatewayAdapter(new FakeFarzinSource([$broken, $good], []));
    $rows = FarzinVocabulary::landInstructions($gateway, null);

    $outcome = app(SyncIngestRecordsJob::class)->handle('farzin', $rows);

    expect($outcome)->toBe(['synced' => 1, 'failed' => 0])
        ->and(IntegrationRecord::where('external_id', '999')->exists())->toBeTrue();
});

it('reports a row as failed when its normalizer throws', function () {
    $registry = app(IngestRegistry::class);
    // A normalizer that rejects everything — stands in for a source
    // glitch or a newly-appeared unmapped field.
    $registry->register('test', 'broken', new class implements Normalizer
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
