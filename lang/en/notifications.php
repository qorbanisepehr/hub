<?php

return [
    'questionnaire_submitted' => [
        'title' => 'New questionnaire submission',
        'body' => 'A questionnaire has been submitted and is waiting for review.',
        'email_action' => 'View questionnaire',
        'email_salutation' => 'Regards,',
    ],

    'auth_login_failed' => [
        'title' => 'Failed sign-in to your account',
        'body' => 'Someone failed to sign in to your account. If this was not you, change your password.',
    ],

    'boardmeeting_summary_ready' => [
        'title' => 'A meeting summary is ready to review',
        'body' => 'The summary run you asked for finished and is waiting for your decision.',
    ],

    'boardmeeting_summary_failed' => [
        'title' => 'A meeting summary run did not finish',
        'body' => 'The run you asked for could not produce a summary. Open the meeting to try again.',
    ],

    'boardmeeting_summary_batch_finished' => [
        'title' => 'The bulk summary run finished',
        'body' => 'The selected meetings were summarised; the ones that failed can be run again.',
    ],
];
