<?php

use App\Domains\Auth\Notifications\AuthLoginFailedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingBundleFailedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingBundleReadyNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryBatchFinishedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryFailedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryReadyNotification;
use App\Domains\Questionnaire\Notifications\QuestionnaireSubmittedNotification;

return [

    /*
    |--------------------------------------------------------------------------
    | Registered notification types
    |--------------------------------------------------------------------------
    |
    | Every in-app notification type, as the class that owns its content. The
    | Notification domain owns the mechanism and the contract; a business domain
    | owns the vocabulary and payload, and lists its class here.
    |
    | This list is the single enumeration point, so features that must reason
    | about the full set (preferences, retention, settings) never scan
    | namespaces. tests/Arch/NotificationBoundaryTest.php enforces that it stays
    | in sync with the filesystem in both directions.
    |
    */

    'types' => [
        AuthLoginFailedNotification::class,
        // The lowercase `m` in these is deliberate: the canonical type is
        // derived from the class name by splitting on the first underscore, and
        // the natural `BoardMeeting…` spelling would yield a `board.` domain
        // prefix disagreeing with every other board-meeting surface.
        BoardmeetingSummaryBatchFinishedNotification::class,
        BoardmeetingSummaryFailedNotification::class,
        BoardmeetingSummaryReadyNotification::class,
        BoardmeetingBundleFailedNotification::class,
        BoardmeetingBundleReadyNotification::class,
        QuestionnaireSubmittedNotification::class,
    ],

];
