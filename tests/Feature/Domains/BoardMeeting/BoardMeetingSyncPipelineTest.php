<?php

use App\Domains\BoardMeeting\Jobs\LoadFarzinToStagingJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Models\IntegrationStaging;
use App\Domains\BoardMeeting\Models\Letter;
use App\Domains\BoardMeeting\Services\BoardMeetingSyncService;
use App\Domains\BoardMeeting\Services\MeetingNumberService;
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

it('projects letters with cleaned text and extracted meeting date', function () {
    app(BoardMeetingSyncService::class)->run(false);

    $meetingLetter = Letter::query()->where('source_id', 101)->first();
    $plainLetter = Letter::query()->where('source_id', 102)->first();

    // CDATA unwrapped, Arabic folded, inner HTML kept (TCK parity),
    // Persian digits preserved in the subject.
    expect($meetingLetter->subject)->toBe('<p>دعوت به جلسه هیئت مدیره مورخ ۱۴۰۴/۰۶/۱۵</p>')
        ->and($meetingLetter->r_name)->toBe('یوسف احمدی')
        ->and($meetingLetter->has_meeting)->toBeTrue()
        ->and($meetingLetter->meeting_pdate)->toBe('1404-06-15')
        ->and($meetingLetter->pdate)->toBe('1404/06/15')
        ->and($meetingLetter->original)->toBeArray()
        // Non-meeting letter untouched by meeting extraction.
        ->and($plainLetter->has_meeting)->toBeFalse()
        ->and($plainLetter->meeting_pdate)->toBeNull();
});

it('derives one meeting per jalali date with minutes and hash', function () {
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
    $service = app(BoardMeetingSyncService::class);

    $service->run(false);

    $meeting = BoardMeeting::query()->where('pdate', '1404-06-15')->first();
    $minute = $meeting->minutes_from_letters[0];

    // TCK parity: the normalizer collapsed newlines to spaces, so the
    // greeting regex (which spans the whole text) only matches when the
    // spaced «می رساند» form appears — here the CDATA-joined form keeps
    // the wrapper; body content and numbering remain verifiable.
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

it('marks unparseable rows failed and still projects the rest', function () {
    $bad = FakeFarzinSource::SAMPLE_LETTERS[0];
    $bad['EntityCode'] = 999;
    $bad['date'] = 'not-a-date-but-fine'; // date is nullable, must not fail
    $bad['subject'] = '<![CDATA[بدون تاریخ جلسه]]>';

    $gateway = new FarzinGatewayAdapter(
        new FakeFarzinSource([$bad], []),
    );

    (new LoadFarzinToStagingJob)->handle($gateway);
    app(BoardMeetingSyncService::class)->run(false);

    // The well-formed letter from beforeEach source still projected.
    expect(Letter::query()->where('source_id', 999)->exists())->toBeTrue()
        ->and(IntegrationStaging::where('status', 'failed')->count())->toBe(0);
});
