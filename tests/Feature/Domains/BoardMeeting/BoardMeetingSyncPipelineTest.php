<?php

use App\Domains\BoardMeeting\Jobs\LoadFarzinToStagingJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Models\IntegrationStaging;
use App\Domains\BoardMeeting\Services\BoardMeetingSyncService;
use App\Domains\BoardMeeting\Services\MeetingNumberService;
use App\Domains\BoardMeeting\Services\MeetingProjection;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Domains\Ingest\Services\RecordProjector;
use App\Integrations\Farzin\FakeFarzinSource;
use App\Integrations\Farzin\FarzinGatewayAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Deterministic fake source; storage untouched (no archive docs).
    config()->set('board-meeting.driver', 'fake');
    config()->set('board-meeting.archive_disk', 'local');
    config()->set('board-meeting.archive_path', 'archive/approvals-test-'.uniqid());
});

it('lands raw rows in staging idempotently', function () {
    $gateway = new FarzinGatewayAdapter(new FakeFarzinSource);

    $first = (new LoadFarzinToStagingJob)->handle($gateway);
    $second = (new LoadFarzinToStagingJob)->handle($gateway);

    expect($first)->toBe(['landed' => 3, 'changed' => 3, 'unchanged' => 0])
        ->and($second)->toBe(['landed' => 3, 'changed' => 0, 'unchanged' => 3])
        ->and(IntegrationStaging::count())->toBe(3)
        ->and(IntegrationStaging::where('source', 'farzin')->where('status', 'raw')->count())->toBe(3);
});

it('normalizes staged rows into generic records', function () {
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
    (new LoadFarzinToStagingJob)->handle($gateway);
    app(RecordProjector::class)->project(LoadFarzinToStagingJob::SOURCE);
    app(MeetingProjection::class)->project();

    expect(BoardMeeting::query()->where('pdate', '1404-06-15')->exists())->toBeTrue()
        ->and(BoardMeeting::query()->where('pdate', '1404-06-10')->exists())->toBeFalse();
});

it('skips unchanged meetings on re-projection', function () {
    $service = app(BoardMeetingSyncService::class);

    $service->run(false);
    $result = $service->run(false);

    expect($result['meetings'])->toBe(['new' => 0, 'updated' => 0, 'unchanged' => 1])
        ->and($result['staging'])->toBe(['landed' => 3, 'changed' => 0, 'unchanged' => 3])
        // All staging rows are already processed, so the records stage
        // has nothing pending — zero failures.
        ->and($result['records'])->toBe(['records' => 0, 'failed' => 0]);
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

it('skips unmappable rows at the boundary and stages the rest', function () {
    // A row without its primary key cannot be staged — the adapter skips
    // it instead of killing the whole batch.
    $broken = FakeFarzinSource::SAMPLE_LETTERS[0];
    unset($broken['EntityCode']);

    $good = FakeFarzinSource::SAMPLE_LETTERS[0];
    $good['EntityCode'] = 999;

    $gateway = new FarzinGatewayAdapter(new FakeFarzinSource([$broken, $good], []));

    $outcome = (new LoadFarzinToStagingJob)->handle($gateway);

    expect($outcome)->toBe(['landed' => 1, 'changed' => 1, 'unchanged' => 0])
        ->and(IntegrationStaging::where('external_id', '999')->exists())->toBeTrue();
});
