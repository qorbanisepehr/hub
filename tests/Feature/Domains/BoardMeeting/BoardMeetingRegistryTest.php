<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingRegistry;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Approval;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\IncomingMeeting;
use Illuminate\Support\Facades\Storage;

/**
 * The registry's whole job: match on the registry key, refresh the
 * source's columns, and never touch what a person has taken over.
 */
function incoming(array $overrides = []): IncomingMeeting
{
    return new IncomingMeeting(...array_merge([
        'heldOnJalali' => '1404-06-15',
        'heldOn' => '2025-09-06',
        'sequence' => 1,
        'origin' => 'farzin',
        'agenda' => [new AgendaItem(1, 'تصویب صورت‌های مالی')],
        'minutes' => 'متن اول',
        'attendees' => [new Attendee('عضو نمونه', AttendeeRole::Member)],
        'provenance' => ['letter_ids' => [1], 'session_ids' => [2]],
    ], $overrides));
}

beforeEach(function () {
    // The registry writes to the database only; nothing may read a disk.
    Storage::fake('local');
});

it('creates a meeting keyed by the jalali registry date and its sequence', function () {
    $outcome = app(BoardMeetingRegistry::class)->import([incoming()]);

    $meeting = BoardMeeting::query()->sole();

    expect($outcome)->toBe(['created' => 1, 'refreshed' => 0, 'locked' => 0])
        ->and($meeting->held_on_jalali)->toBe('1404-06-15')
        ->and($meeting->sequence)->toBe(1)
        ->and($meeting->origin)->toBe('farzin')
        ->and($meeting->imported_at)->not->toBeNull()
        ->and($meeting->provenance)->toBe(['letter_ids' => [1], 'session_ids' => [2]]);
});

it('keeps two meetings of the same day apart', function () {
    // A meeting is not a date: the same day can hold an extraordinary
    // session, which the old `date UNIQUE` column made impossible.
    app(BoardMeetingRegistry::class)->import([
        incoming(),
        incoming(['sequence' => 2, 'minutes' => 'متن فوق‌العاده']),
    ]);

    expect(BoardMeeting::query()->count())->toBe(2)
        ->and(BoardMeeting::query()->where('sequence', 2)->sole()->minutes)->toBe('متن فوق‌العاده');
});

it('round-trips the typed lists through their casts', function () {
    app(BoardMeetingRegistry::class)->import([incoming()]);

    $meeting = BoardMeeting::query()->sole();

    expect($meeting->agenda[0])->toBeInstanceOf(AgendaItem::class)
        ->and($meeting->agenda[0]->position)->toBe(1)
        ->and($meeting->attendees[0])->toBeInstanceOf(Attendee::class)
        ->and($meeting->attendees[0]->role)->toBe(AttendeeRole::Member);
});

it('round-trips approvals, which no import may write', function () {
    // `approvals` has no field on `IncomingMeeting`, so the cast's only
    // writer today is the one-time archive migration. It still has to
    // survive a round trip through the JSONB column (ADR-011 decision 9).
    BoardMeeting::query()->create([
        'held_on' => '2025-09-06',
        'held_on_jalali' => '1404-06-15',
        'sequence' => 1,
        'origin' => 'farzin',
        'approvals' => [new Approval('1234/ص/1404', 'صورت‌های مالی', 12)],
    ]);

    $approval = BoardMeeting::query()->sole()->approvals[0];

    expect($approval)->toBeInstanceOf(Approval::class)
        ->and($approval->number)->toBe('1234/ص/1404')
        ->and($approval->title)->toBe('صورت‌های مالی')
        ->and($approval->documentId)->toBe(12);
});

it('refreshes the source columns on a re-import instead of duplicating', function () {
    $registry = app(BoardMeetingRegistry::class);

    $first = $registry->import([incoming()]);
    $second = $registry->import([incoming(['agenda' => [new AgendaItem(1, 'دستور تازه')]])]);

    expect($first['created'])->toBe(1)
        ->and($second)->toBe(['created' => 0, 'refreshed' => 1, 'locked' => 0])
        ->and(BoardMeeting::query()->count())->toBe(1)
        ->and(BoardMeeting::query()->sole()->agenda[0]->title)->toBe('دستور تازه');
});

it('refreshes the derived text while nobody has edited the row', function () {
    $registry = app(BoardMeetingRegistry::class);

    $registry->import([incoming()]);
    $registry->import([incoming(['minutes' => 'متن اصلاح‌شدهٔ منبع'])]);

    expect(BoardMeeting::query()->sole()->minutes)->toBe('متن اصلاح‌شدهٔ منبع');
});

it('locks the row on the first human edit and never reverts it', function () {
    $registry = app(BoardMeetingRegistry::class);
    $registry->import([incoming()]);

    $meeting = BoardMeeting::query()->sole();
    $meeting->update(['title' => 'عنوان انسانی', 'minutes' => 'صورتجلسهٔ ویرایش‌شده']);

    expect($meeting->refresh()->source_locked_at)->not->toBeNull();

    $outcome = $registry->import([incoming(['minutes' => 'متن تازهٔ منبع'])]);

    expect($outcome)->toBe(['created' => 0, 'refreshed' => 1, 'locked' => 1])
        ->and($meeting->refresh()->minutes)->toBe('صورتجلسهٔ ویرایش‌شده')
        ->and($meeting->title)->toBe('عنوان انسانی');
});

it('keeps a locked meeting\'s human columns while its source columns still refresh', function () {
    $registry = app(BoardMeetingRegistry::class);
    $registry->import([incoming()]);

    BoardMeeting::query()->sole()->update(['attendees' => [new Attendee('ویرایش انسانی', AttendeeRole::Chair)]]);

    $registry->import([incoming(['agenda' => [new AgendaItem(2, 'دستور تازه')]])]);

    $meeting = BoardMeeting::query()->sole();

    expect($meeting->attendees[0]->name)->toBe('ویرایش انسانی')
        ->and($meeting->agenda[0]->title)->toBe('دستور تازه');
});

it('never deletes a meeting the source stopped carrying', function () {
    $registry = app(BoardMeetingRegistry::class);

    $registry->import([incoming(), incoming(['sequence' => 2])]);
    $registry->import([incoming()]);

    expect(BoardMeeting::query()->count())->toBe(2);
});

it('rejects a snapshot that cannot be a registry key', function () {
    $registry = app(BoardMeetingRegistry::class);

    expect(fn () => $registry->import([incoming(['heldOnJalali' => '15/06/1404'])]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $registry->import([incoming(['sequence' => 0])]))
        ->toThrow(InvalidArgumentException::class);
});
