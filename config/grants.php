<?php

use App\Domains\Cv\Models\Cv;
use App\Domains\Questionnaire\Models\Questionnaire;

return [
    /*
    |--------------------------------------------------------------------------
    | Grant Entity Registry
    |--------------------------------------------------------------------------
    |
    | Maps a grant entity key to the model and OTP channel used to protect it.
    | Entities are addressed as `{entity}:{purpose}` via the `grant.access`
    | middleware (e.g. `grant.access:questionnaire,edit`).
    |
    | `view_permission` is the HR-side permission accepted by `grant.serve`
    | when serving documents of that entity to authenticated users.
    |
    | Adding a new protected entity (e.g. a CV) is a single line here plus its
    | own routes.
    |
    */

    'entities' => [
        'questionnaire' => [
            'model' => Questionnaire::class,
            'channel' => 'mobile',
            'view_permission' => 'questionnaire.view',
        ],
        'cv' => [
            'model' => Cv::class,
            'channel' => 'mobile',
            'view_permission' => 'cv.view',
        ],
    ],
];
