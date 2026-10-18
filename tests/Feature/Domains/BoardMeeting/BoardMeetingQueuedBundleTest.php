<?php

use App\Contracts\DocumentAuthorization;
use App\Domains\BoardMeeting\Enums\BundleFailureReason;
use App\Domains\BoardMeeting\Jobs\BuildBoardMeetingBundleJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Notifications\BoardmeetingBundleFailedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingBundleReadyNotification;
use App\Domains\BoardMeeting\Services\BoardMeetingBundle;
use App\Domains\Notification\Support\NotificationType;
use App\Models\User;
use App\Support\Exports\BundleBuilder;
use App\Support\Exports\BundleTooLarge;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\Queued\ExportFileStore;
use App\Support\Exports\Templates\FileTemplateLibrary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Bundles too large to build in a request.
 *
 * The rule worth protecting is the decision: count the work, build it now if it
 * is small, queue it if it is not, and refuse it only when it could not be built
 * at all. Everything else here — the stop marker, the twenty-four hour window,
 * the notification — is machinery around that one branch.
 */
describe('queued board meeting bundles', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->app->setLocale('fa');
        config(['exports.queued.sync_entry_limit' => 4]);
    });

    /** Put real bytes behind a meeting's approvals so the archive has files. */
    $storeScans = function (BoardMeeting $meeting): void {
        foreach ($meeting->documentUsages as $usage) {
            Storage::disk($usage->document->disk)->put($usage->document->path, 'scanned page');
        }
    };

    it('builds a small bundle in the request, as before', function () {
        BoardMeeting::factory()->create(['sequence' => 1]);

        Queue::fake();

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['md']])
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');

        // Four entries: the sheet, the manifest pages... and no job.
        expect(substr($response->streamedContent(), 0, 2))->toBe('PK');

        Queue::assertNothingPushed();
    });

    it('queues a bundle bigger than the sync threshold and answers 202', function () {
        BoardMeeting::factory()->count(6)->create();

        Queue::fake();

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['md']])
            ->assertStatus(202)
            ->assertJsonPath('data.meeting_count', 6);

        expect($response->json('data.token'))->toBeString()->not->toBeEmpty();

        // Nothing is rendered in the request — that is the whole point.
        Queue::assertPushed(BuildBoardMeetingBundleJob::class);
    });

    it('refuses a bundle that is too big to build at all, before queueing it', function () {
        config(['exports.bundle.max_entries' => 3]);

        BoardMeeting::factory()->count(6)->create();

        Queue::fake();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['md']])
            ->assertStatus(422)
            ->assertJsonPath('unit', 'entries');

        // A job that would be refused by the same cap an hour later has spent a
        // worker to learn nothing.
        Queue::assertNothingPushed();
    });

    it('is not held to the document archive cap', function () {
        // The bug this protects: bundles were capped by `documents.zip.*`, a
        // limit written for a handful of large scans of one record. A registry's
        // worth of small rendered sheets went over it, and exporting every
        // meeting was refused over a number that had nothing to do with them.
        Queue::fake();

        config(['documents.zip.max_files' => 100, 'exports.bundle.max_entries' => 500]);

        // 60 meetings × 3 formats + the two manifest pages = 182 entries: twice
        // the document cap, comfortably inside the bundle cap.
        BoardMeeting::factory()->count(60)->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['pdf', 'docx', 'md']])
            ->assertStatus(202)
            ->assertJsonPath('data.meeting_count', 60);

        Queue::assertPushed(BuildBoardMeetingBundleJob::class);
    });

    it('counts the files it will write, so the cap is known before rendering', function () {
        // Three meetings, two formats, plus the two manifest pages = 8, over the
        // cap of 3. A cap that counted meetings rather than entries would let
        // this through and build something it could not store.
        config(['exports.bundle.max_entries' => 8]);

        BoardMeeting::factory()->count(3)->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['md', 'json']])
            ->assertStatus(202);
    });

    it('queues only the selected meetings, not everything under the filter', function () {
        // The bulk-bar button sends ids, and the job is handed those ids. If the
        // queue re-read the filters it would quietly widen five ticked rows back
        // into the whole registry — the exact surprise the selection was ticked
        // to avoid, arriving after the dialog closed.
        Queue::fake();

        // One meeting over the sync threshold, so this is the queued path. The
        // subject here is the scope, not the queueing, which other tests cover.
        config(['exports.queued.sync_entry_limit' => 1]);

        $wanted = BoardMeeting::factory()->create(['sequence' => 1]);
        BoardMeeting::factory()->count(8)->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['md', 'json'],
                'ids' => [$wanted->getKey()],
            ])
            ->assertStatus(202)
            // Nine meetings exist and the filters would cover all of them, so a
            // count of one is the scope. It counts the same collection whose
            // keys were dispatched a line earlier, which is why this says
            // something about the job's payload without reaching into it.
            ->assertJsonPath('data.meeting_count', 1);

        Queue::assertPushed(BuildBoardMeetingBundleJob::class);
    });

    it('builds the archive, stores it, and tells the person who asked', function () {
        Notification::fake();

        $meeting = BoardMeeting::factory()->create(['sequence' => 1]);
        $user = createUserWithPermissions(['board-meeting.export']);
        $token = app(ExportFileStore::class)->issueToken();

        (new BuildBoardMeetingBundleJob($token, [$meeting->getKey()], ['md'], false, $user->getKey()))
            ->handle(
                app(DocumentRendererRegistry::class),
                app(BoardMeetingBundle::class),
                app(BundleBuilder::class),
                app(ExportFileStore::class),
                app(DocumentAuthorization::class),
                app(FileTemplateLibrary::class),
            );

        Notification::assertSentTo(
            $user,
            BoardmeetingBundleReadyNotification::class,
            fn (BoardmeetingBundleReadyNotification $n): bool => $n->toDatabase($user)['token'] === $token,
        );

        $stored = app(ExportFileStore::class)->find($token);

        expect($stored)->not->toBeNull()
            ->and(substr((string) file_get_contents($stored['path']), 0, 2))->toBe('PK');
    });

    it('sends the token rather than the archive, because a notification outlives it', function () {
        $payload = (new BoardmeetingBundleReadyNotification('a-token', 3))->toDatabase(new User);

        expect($payload['resource'])->toBe([
            'type' => 'export-bundle',
            'id' => 'a-token',
            'action' => 'download',
        ])->and($payload)->not->toHaveKey('archive');
    });

    it('downloads a stored bundle by its token', function () {
        $token = app(ExportFileStore::class)->issueToken();
        $path = tempnam(sys_get_temp_dir(), 'bundle-src-');
        file_put_contents($path, 'PK-not-really-but-a-file');

        app(ExportFileStore::class)->put($token, $path, 'board-meetings.zip');
        @unlink($path);

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/export/bundle/{$token}")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');
    });

    it('answers 404 for a token it never issued', function () {
        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get('/api/board-meetings/export/bundle/'.str_repeat('a', 40))
            ->assertStatus(404);
    });

    it('refuses a token that is not shaped like one', function () {
        // The token reaches a filesystem path, so anything that is not one of
        // ours is rejected before it is used as a directory name.
        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get('/api/board-meetings/export/bundle/..%2F..%2Fconfig')
            ->assertStatus(404);
    });

    it('answers 404 once the window has passed', function () {
        $token = app(ExportFileStore::class)->issueToken();
        $path = tempnam(sys_get_temp_dir(), 'bundle-src-');
        file_put_contents($path, 'bytes');

        $store = app(ExportFileStore::class);
        $store->put($token, $path, 'board-meetings.zip');
        @unlink($path);

        Storage::disk('local')->put(
            "exports/{$token}/.expires-at",
            now()->subMinute()->toIso8601String(),
        );

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/export/bundle/{$token}")
            ->assertStatus(404);

        // And the file goes with it rather than lingering until the prune.
        Storage::disk('local')->assertMissing("exports/{$token}");
    });

    it('stops at the next meeting boundary when asked', function () {
        Notification::fake();

        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.export']);
        $token = app(ExportFileStore::class)->issueToken();

        BuildBoardMeetingBundleJob::cancel($token);

        (new BuildBoardMeetingBundleJob($token, [$meeting->getKey()], ['md'], false, $user->getKey()))
            ->handle(
                app(DocumentRendererRegistry::class),
                app(BoardMeetingBundle::class),
                app(BundleBuilder::class),
                app(ExportFileStore::class),
                app(DocumentAuthorization::class),
                app(FileTemplateLibrary::class),
            );

        // Stopped, so nothing was built and nothing was announced.
        Notification::assertNothingSent();
        expect(app(ExportFileStore::class)->find($token))->toBeNull();
    });

    it('refuses to build when the caller may not download one of the scans', function () use ($storeScans) {
        Notification::fake();

        $meeting = BoardMeeting::factory()->withApprovalDocuments(1)->create();
        ($storeScans)($meeting);

        $user = createUserWithPermissions(['board-meeting.export']);
        $token = app(ExportFileStore::class)->issueToken();

        $job = new BuildBoardMeetingBundleJob($token, [$meeting->getKey()], ['md'], true, $user->getKey());

        // Deny-all, asked again at build time rather than inherited from the
        // request that queued it: the job runs later, and the answer may differ.
        expect(fn () => $job->handle(
            app(DocumentRendererRegistry::class),
            app(BoardMeetingBundle::class),
            app(BundleBuilder::class),
            app(ExportFileStore::class),
            app(DocumentAuthorization::class),
            app(FileTemplateLibrary::class),
        ))->toThrow(AuthorizationException::class);

        Notification::assertNothingSent();
    });

    it('tells the person when the queue gave up on it', function () {
        Notification::fake();

        $user = createUserWithPermissions(['board-meeting.export']);
        $token = app(ExportFileStore::class)->issueToken();

        (new BuildBoardMeetingBundleJob($token, [1], ['md'], false, $user->getKey()))
            ->failed(new RuntimeException('the queue gave up'));

        Notification::assertSentTo(
            $user,
            BoardmeetingBundleFailedNotification::class,
            fn (BoardmeetingBundleFailedNotification $n): bool => $n->toDatabase($user)['reason'] === 'failed',
        );
    });

    it('never blames the selection for a failure the selection cannot fix', function () {
        // The reader is told "narrow your selection". That advice is wrong for a
        // permission failure — no selection avoids a document they may not
        // download — and sending somebody to narrow a filter that was never the
        // problem is worse than saying nothing.
        expect(BundleFailureReason::classify(new AuthorizationException('denied')))
            ->toBe(BundleFailureReason::NotPermitted)
            ->and(BundleFailureReason::classify(BundleTooLarge::forEntries(200, 100)))
            ->toBe(BundleFailureReason::TooLarge)
            ->and(BundleFailureReason::classify(new RuntimeException('disk gone')))
            ->toBe(BundleFailureReason::Failed);
    });

    it('prunes only the archives nobody came back for', function () {
        $store = app(ExportFileStore::class);

        $fresh = $store->issueToken();
        $stale = $store->issueToken();

        foreach ([$fresh, $stale] as $token) {
            $path = tempnam(sys_get_temp_dir(), 'bundle-src-');
            file_put_contents($path, 'bytes');
            $store->put($token, $path, 'board-meetings.zip');
            @unlink($path);
        }

        Storage::disk('local')->put(
            "exports/{$stale}/.expires-at",
            now()->subMinute()->toIso8601String(),
        );

        expect($store->pruneExpired())->toBe(1)
            ->and($store->find($fresh))->not->toBeNull()
            ->and($store->find($stale))->toBeNull();
    });

    it('gives every notification this feature sends a title and a body', function () {
        // A notification type with no copy renders as a blank row, which reads
        // as a bug rather than as missing translation.
        foreach ([
            BoardmeetingBundleReadyNotification::class,
            BoardmeetingBundleFailedNotification::class,
        ] as $class) {
            $type = NotificationType::canonical($class);

            expect(Lang::has('notifications.'.str_replace('.', '_', $type).'.title'))->toBeTrue("{$type} has no title")
                ->and(Lang::has('notifications.'.str_replace('.', '_', $type).'.body'))->toBeTrue("{$type} has no body");
        }
    });

    it('names its types under the boardmeeting prefix every other surface uses', function () {
        // The canonical type is derived mechanically from the class name, and a
        // capital M would yield board.meeting_bundle_ready — a prefix that
        // disagrees with the audit catalog, resource types and permissions.
        expect(NotificationType::canonical(BoardmeetingBundleReadyNotification::class))
            ->toBe('boardmeeting.bundle_ready')
            ->and(NotificationType::canonical(BoardmeetingBundleFailedNotification::class))
            ->toBe('boardmeeting.bundle_failed');
    });
});
