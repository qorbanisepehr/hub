<?php

namespace App\Domains\BoardMeeting\Events;

use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Events\BaseAuditEvent;

/**
 * One summary run finished and reported. One event per run, whatever became of
 * it (D22) — including a run that only proposed, and including one that
 * produced nothing, because "we asked the model and here is what it said" is
 * the fact the registry's history has to be able to answer. The Audit domain
 * already gives that history a searchable home, so this needs no table of its
 * own.
 *
 * The mode is in the metadata rather than in the event name because it changes
 * what the run *did*, not what kind of thing happened: reviewing and applying
 * are the same act done to the record in two different degrees, and splitting
 * them into two event names would double the catalog to say so.
 *
 * The run is described, not the row. After an `apply` the row is a merge of
 * this run and several earlier ones, and recording the row would quietly turn
 * every field's history into the history of whichever run touched it last.
 *
 * Naming follows the hub convention (EventCatalogTest): {domain}.{subject}_{action}
 * with the domain segment as the folder name lowercased.
 */
class MeetingSummaryGenerated extends BaseAuditEvent
{
    /**
     * @param  int|null  $requestedBy  who asked for this run. Null for a run
     *                                 dispatched from a scheduled or CLI path,
     *                                 which has nobody behind it. Recorded
     *                                 because it is a fact about the run that
     *                                 the audit row would otherwise lose, and
     *                                 because the notification listener needs
     *                                 it to know who to tell
     * @param  string|null  $batchId  set when the run was one of a bulk
     *                                dispatch, so a batch of runs can be
     *                                summarised once instead of notifying per
     *                                meeting. Null for a run started from one
     *                                meeting's page
     */
    public function __construct(
        private readonly BoardMeeting $meeting,
        private readonly MeetingSummary $run,
        private readonly SummaryWriteMode $mode,
        private readonly ?int $requestedBy = null,
        private readonly ?string $batchId = null,
    ) {}

    public function requestedBy(): ?int
    {
        return $this->requestedBy;
    }

    public function batchId(): ?string
    {
        return $this->batchId;
    }

    public function eventName(): string
    {
        return 'boardmeeting.summary_generated';
    }

    public function category(): string
    {
        return 'boardmeeting';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'board-meeting',
            'id' => $this->meeting->getKey(),
        ];
    }

    public function description(): ?string
    {
        return sprintf(
            'Board meeting %d summary run finished (%s): %s',
            $this->meeting->getKey(),
            $this->mode->value,
            $this->run->status,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array
    {
        return [
            'mode' => $this->mode->value,
            'status' => $this->run->status,
            'error' => $this->run->error,
            // Who asked, when somebody did. Kept out of the description because
            // the audit row already carries the actor for a human-initiated
            // run; this records the requester for the ones dispatched from a
            // queue, where there is no request context to resolve one from.
            'requested_by' => $this->requestedBy,
            // Present only for a bulk dispatch. Null rather than omitted for a
            // single run, so a reader can tell "not part of a batch" from "this
            // shape predates batches".
            'batch_id' => $this->batchId,
            'requested_fields' => array_keys($this->run->fields),
            'filled_fields' => array_keys(array_filter(
                $this->run->fields,
                fn (array $state): bool => ($state['status'] ?? null) === MeetingSummary::FIELD_OK,
            )),
            'provider' => $this->run->provider,
            'model' => $this->run->model,
            'usage' => $this->run->usage,
        ];
    }
}
