<?php

use App\Domains\BoardMeeting\Controllers\BoardMeetingController;
use App\Domains\BoardMeeting\Controllers\BoardMeetingDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('board-meetings', [BoardMeetingController::class, 'index'])
        ->name('board-meetings.index')
        ->middleware('permission:board-meeting.view');

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
});
