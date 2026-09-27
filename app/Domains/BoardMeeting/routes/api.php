<?php

use App\Domains\BoardMeeting\Controllers\BoardMeetingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('board-meetings', [BoardMeetingController::class, 'index'])
        ->name('board-meetings.index')
        ->middleware('permission:board-meeting.view');

    Route::get('board-meetings/{meeting}', [BoardMeetingController::class, 'show'])
        ->name('board-meetings.show')
        ->middleware('permission:board-meeting.view');
});
