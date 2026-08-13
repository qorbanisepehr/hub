<?php

use App\Domains\Cv\Repositories\CvRepository;
use App\Domains\Cv\Services\CvService;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepository;
use App\Domains\Questionnaire\Services\QuestionnaireService;

arch('document domain never depends on other domains services or repositories')
    ->expect('App\Domains\Document')
    ->not->toUse([
        CvService::class,
        CvRepository::class,
        EmployeeService::class,
        QuestionnaireService::class,
        QuestionnaireRepository::class,
    ]);
