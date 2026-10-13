<?php

use App\Domains\BoardMeeting\Controllers\BoardMeetingController;
use App\Domains\BoardMeeting\Controllers\BoardMeetingDocumentController;
use App\Domains\BoardMeeting\Controllers\MeetingSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('board-meetings', [BoardMeetingController::class, 'index'])
        ->name('board-meetings.index')
        ->middleware('permission:board-meeting.view');

    // The source behind the sync is the integration's business, so the
    // route is declared before `{meeting}` and needs no source knowledge.
    Route::post('board-meetings/sync', [BoardMeetingController::class, 'sync'])
        ->name('board-meetings.sync')
        ->middleware('permission:board-meeting.sync');

    // Export: also declared before `{meeting}` (a literal path must never
    // be captured by the parameter).
    Route::get('board-meetings/export/fields', [BoardMeetingController::class, 'exportFields'])
        ->name('board-meetings.export-fields')
        ->middleware('permission:board-meeting.export');

    Route::get('board-meetings/export', [BoardMeetingController::class, 'export'])
        ->name('board-meetings.export')
        ->middleware('permission:board-meeting.export');

    // One meeting as a printable PDF, on `board-meeting.export` like the
    // registry export above: taking a copy of a meeting away is that
    // permission's job, whether the copy is a table of many or one sheet.
    Route::get('board-meetings/{meeting}/export/pdf', [BoardMeetingController::class, 'exportPdf'])
        ->name('board-meetings.export.pdf')
        ->middleware('permission:board-meeting.export');

    Route::get('board-meetings/{meeting}', [BoardMeetingController::class, 'show'])
        ->name('board-meetings.show')
        ->middleware('permission:board-meeting.view');

    Route::put('board-meetings/{meeting}', [BoardMeetingController::class, 'update'])
        ->name('board-meetings.update')
        ->middleware('permission:board-meeting.update');

    // Documents (K4): scoped to a resolved meeting, flat placements.
    Route::get('board-meetings/{meeting}/documents', [BoardMeetingDocumentController::class, 'index'])
        ->name('board-meetings.documents.index')
        ->middleware('permission:board-meeting.documents.view');

    Route::post('board-meetings/{meeting}/documents', [BoardMeetingDocumentController::class, 'store'])
        ->name('board-meetings.documents.store')
        ->middleware('permission:board-meeting.documents.upload');

    // Bulk import (idea basket, D30 — the only path new مصوب enter the hub).
    // Declared before `{usage}`: a literal path segment must never be bound
    // as the usage parameter of the delete route below.
    Route::post('board-meetings/{meeting}/documents/bulk', [BoardMeetingDocumentController::class, 'bulkStore'])
        ->name('board-meetings.documents.bulk')
        ->middleware('permission:board-meeting.documents.upload');

    Route::delete('board-meetings/{meeting}/documents/{usage}', [BoardMeetingDocumentController::class, 'destroy'])
        ->name('board-meetings.documents.destroy')
        ->middleware('permission:board-meeting.documents.delete');

    // File serving is uuid-addressed (documentPayload url/download_url).
    Route::get('board-meetings/documents/{uuid}/serve', [BoardMeetingDocumentController::class, 'serve'])
        ->name('board-meeting.documents.serve')
        ->middleware('permission:board-meeting.documents.download');

    // AI summary. Both paths write the meeting's own record, so both sit on
    // `board-meeting.update` rather than a permission of their own: asking for
    // a run and deciding what it produced are the same write, at two moments.
    Route::post('board-meetings/{meeting}/summary', [MeetingSummaryController::class, 'store'])
        ->name('board-meetings.summary.store')
        ->middleware('permission:board-meeting.update');

    Route::post('board-meetings/{meeting}/summary/accept', [MeetingSummaryController::class, 'accept'])
        ->name('board-meetings.summary.accept')
        ->middleware('permission:board-meeting.update');

    // Refusing a proposal, which is not the same request as accepting an empty
    // selection and does not share its endpoint. Accept has to carry the
    // meeting's tags in full, so a person with no opinion about tags has
    // nothing they could send; routing refusal through accept made them repeat
    // the tag list to say "none of this", and re-wrote the pivot on the way.
    Route::post('board-meetings/{meeting}/summary/dismiss', [MeetingSummaryController::class, 'dismiss'])
        ->name('board-meetings.summary.dismiss')
        ->middleware('permission:board-meeting.update');

    // Stopping a run that is queued or under way. Same permission as the other
    // three: it is a write to this meeting's record made by somebody allowed to
    // curate it. It is not a fourth kind of decision - a decision needs a
    // proposal to decide about, and this is for when there is not one yet.
    Route::post('board-meetings/{meeting}/summary/cancel', [MeetingSummaryController::class, 'cancel'])
        ->name('board-meetings.summary.cancel')
        ->middleware('permission:board-meeting.update');

    // The registry-level pair, on the sync permission rather than update: both
    // act on the whole registry instead of one row, so they belong beside the
    // registry sync rather than beside a single meeting's write.
    Route::get('board-meetings/summary/candidates', [MeetingSummaryController::class, 'candidates'])
        ->name('board-meetings.summary.candidates')
        ->middleware('permission:board-meeting.sync');

    Route::post('board-meetings/summary/bulk', [MeetingSummaryController::class, 'bulk'])
        ->name('board-meetings.summary.bulk')
        ->middleware('permission:board-meeting.sync');

    // Stopping the part of a bulk that has not run yet. On the sync permission
    // for the same reason the dispatch is: it acts on a registry-wide batch
    // rather than on one row. Declared after the literal segments above so a
    // batch id can never be read as one of them.
    Route::post('board-meetings/summary/batches/{batchId}/cancel', [MeetingSummaryController::class, 'cancelBatch'])
        ->name('board-meetings.summary.batch-cancel')
        ->middleware('permission:board-meeting.sync');

    // The batches still outstanding, so a page that was reloaded can still find
    // the one it dispatched. A read, but on the same permission as the stop it
    // makes reachable rather than on `view`: it exists to feed that control,
    // and what it describes is registry-wide work, not one meeting's record.
    Route::get('board-meetings/summary/batches', [MeetingSummaryController::class, 'liveBatches'])
        ->name('board-meetings.summary.batches')
        ->middleware('permission:board-meeting.sync');
});
