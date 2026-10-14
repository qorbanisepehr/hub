<?php

namespace App\Domains\BoardMeeting\Jobs;

use App\Contracts\DocumentAuthorization;
use App\Domains\BoardMeeting\Enums\BundleFailureReason;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Notifications\BoardmeetingBundleFailedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingBundleReadyNotification;
use App\Domains\BoardMeeting\Services\BoardMeetingBundle;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Models\DocumentUsage;
use App\Models\User;
use App\Support\Exports\BundleBuilder;
use App\Support\Exports\BundleRequest;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\Queued\ExportFileStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Builds a bundle too large to build inside a request, and tells the person who
 * asked when it is ready.
 *
 * SYNCHRONOUS BUNDLES HOLD A WORKER. Rendering fifty meetings into three formats
 * each is a minute of somebody's queue, and a browser waiting on it is a request
 * that can time out. So the controller counts the work before it renders
 * anything and hands the rest to this.
 *
 * THE SELECTION IS FROZEN IN IDS. The caller resolves "everything the list can
 * show" to concrete meeting ids at request time and passes those, rather than
 * passing the filters and letting them be re-read later. Two reasons, and the
 * second is the one that matters: a filter means something different after a
 * deploy than it did when it was clicked, so a job that re-read it can build a
 * different archive than the one that was asked for; and the ids are what the
 * authorization below is actually checked against, so what gets stored and what
 * gets permitted are the same list rather than two that might drift apart.
 *
 * AUTHORIZATION HAPPENS HERE, NOT AT REQUEST TIME. Deliberately. The request
 * that queued this checked only that the caller may export; whether they may
 * download the *documents* of every one of these meetings is asked again, per
 * usage and deny-all, because a job running an hour later must not inherit an
 * hour-old answer about a permission the caller may since have lost. A meeting
 * whose scans they may not have refuses the whole archive, exactly as it does
 * in the synchronous path.
 */
final class BuildBoardMeetingBundleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Cooperative cancellation, checked between meetings. */
    private const CANCEL_KEY = 'board-meeting:export-bundle-cancelled:';

    /**
     * @param  list<int>  $meetingIds  frozen at request time — see the class docblock
     * @param  list<string>  $formats
     */
    public function __construct(
        private readonly string $token,
        private readonly array $meetingIds,
        private readonly array $formats,
        private readonly bool $documents,
        private readonly ?int $requestedBy,
    ) {}

    /**
     * Ask a running bundle to stop.
     *
     * Cooperative, for the same reason the summary runs are: this job cannot be
     * interrupted from another process mid-render, so the promise it can keep
     * is "at the next meeting boundary", not "now". The token is issued when the
     * bundle is queued rather than when it is built, which is what makes a
     * bundle that is still rendering stoppable at all.
     */
    public static function cancel(string $token): void
    {
        Cache::put(self::CANCEL_KEY.$token, true, now()->addDay());
    }

    /** Whether somebody has asked this bundle to stop. */
    public function isCancelled(): bool
    {
        return Cache::has(self::CANCEL_KEY.$this->token);
    }

    public function handle(
        DocumentRendererRegistry $renderers,
        BoardMeetingBundle $bundle,
        BundleBuilder $bundles,
        ExportFileStore $store,
        DocumentAuthorization $documentAuthorization,
    ): void {
        $meetings = BoardMeeting::query()
            ->with('documentUsages.document.category')
            ->whereIn('id', $this->meetingIds)
            ->orderBy('id')
            ->get();

        // Stopped, or nothing left to build. Either way the token is cleaned up
        // so a stale archive can never appear under it later.
        if ($this->isCancelled() || $meetings->isEmpty()) {
            $store->delete($this->token);

            return;
        }

        if ($this->documents) {
            $this->authorizeEveryUsage($meetings, $documentAuthorization);
        }

        $selection = $this->documents
            ? BundleRequest::sheetsWithAttachments($renderers, $this->formats)
            : BundleRequest::sheets($renderers, $this->formats);

        $entries = $bundle->entries($meetings->all(), $selection);
        $entries = [...$entries, ...$bundle->manifestEntries(
            $bundle->manifest($meetings->all(), $entries, $this->documents),
        )];

        // The last check before the expensive part. Stopping between meetings is
        // worth catching; stopping after the last one is not, because there is
        // nothing left to stop.
        if ($this->isCancelled()) {
            $store->delete($this->token);

            return;
        }

        try {
            $built = $bundles->build(
                $entries,
                filename: 'board-meetings.zip',
                maxEntries: (int) config('exports.bundle.max_entries'),
                maxBytes: (int) config('exports.bundle.max_bytes'),
            );
        } catch (Throwable $error) {
            $store->delete($this->token);

            throw $error;
        }

        if ($this->isCancelled()) {
            $built->delete();
            $store->delete($this->token);

            return;
        }

        $store->put($this->token, $built->path, 'board-meetings.zip');
        $built->delete();

        $this->recipient()?->notify(new BoardmeetingBundleReadyNotification(
            $this->token,
            $meetings->count(),
        ));
    }

    /**
     * The queue gave up on this bundle.
     *
     * Anything it may have written is removed: a half-built archive that nobody
     * was told about is the kind of thing that sits on disk until a prune
     * eventually happens to reach it. And the person who asked is told, because
     * silence after clicking a button is the one outcome they cannot act on.
     */
    public function failed(Throwable $error): void
    {
        app(ExportFileStore::class)->delete($this->token);

        Cache::forget(self::CANCEL_KEY.$this->token);

        $this->recipient()?->notify(new BoardmeetingBundleFailedNotification(
            $this->token,
            BundleFailureReason::classify($error),
        ));
    }

    /**
     * @param  Collection<int, BoardMeeting>  $meetings
     *
     * @throws AuthorizationException when the caller may not download some
     *                                meeting's documents — deny-all, so the
     *                                archive is refused rather than thinned
     */
    private function authorizeEveryUsage(
        Collection $meetings,
        DocumentAuthorization $documentAuthorization,
    ): void {
        $actor = $this->recipient();

        if ($actor === null) {
            return;
        }

        foreach ($meetings as $meeting) {
            foreach ($meeting->documentUsages as $usage) {
                if ($usage instanceof DocumentUsage && ! $documentAuthorization->authorize(
                    $actor,
                    DocumentAction::Download,
                    DocumentAuthorizationContext::forUsage($usage),
                )) {
                    throw new AuthorizationException(__('messages.permission_denied'));
                }
            }
        }
    }

    private function recipient(): ?User
    {
        return $this->requestedBy === null ? null : User::find($this->requestedBy);
    }
}
