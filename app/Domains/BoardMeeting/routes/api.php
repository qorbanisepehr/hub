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
});
