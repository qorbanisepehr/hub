<?php

use App\Domains\Auth\Notifications\AuthLoginFailedNotification;
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
        QuestionnaireSubmittedNotification::class,
    ],

];
