<?php

use App\Contracts\DocumentAuthorization;
use App\Domains\Cv\Models\Cv;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Services\DocumentCapabilities;
use App\Domains\Employee\Models\Employee;
use App\Domains\Questionnaire\Models\Questionnaire;

beforeEach(function () {
    $this->documentAuthorization = app(DocumentAuthorization::class);
    $this->capabilities = app(DocumentCapabilities::class);
});

it('registers independent cv and questionnaire document permission groups', function () {
    $groups = config('permissions.groups');

    expect(array_keys($groups))->toContain('cv.documents')
        ->toContain('questionnaire.documents');
    expect(array_keys($groups['cv.documents']['permissions']))
        ->toContain('cv.documents.view')
        ->toContain('cv.documents.upload')
        ->toContain('cv.documents.download')
        ->toContain('cv.documents.delete');
    expect(array_keys($groups['questionnaire.documents']['permissions']))
        ->toContain('questionnaire.documents.view')
        ->toContain('questionnaire.documents.upload')
        ->toContain('questionnaire.documents.download')
        ->toContain('questionnaire.documents.delete');
});

it('authorizes cv documents under the cv permission namespace', function () {
    $user = createUserWithPermissions(['cv.documents.view']);
    $cv = Cv::create([]);

    expect($this->documentAuthorization->authorize(
        $user,
        DocumentAction::View,
        DocumentAuthorizationContext::forOwner($cv),
    ))->toBeTrue();
});

it('does not let the employee permission grant access to cv documents', function () {
    $user = createUserWithPermissions(['employee.documents.view']);
    $cv = Cv::create([]);

    expect($this->documentAuthorization->authorize(
        $user,
        DocumentAction::View,
        DocumentAuthorizationContext::forOwner($cv),
    ))->toBeFalse();
});

it('does not let the cv permission grant access to employee documents', function () {
    $user = createUserWithPermissions(['cv.documents.view']);
    $employee = Employee::factory()->create();

    expect($this->documentAuthorization->authorize(
        $user,
        DocumentAction::View,
        DocumentAuthorizationContext::forOwner($employee),
    ))->toBeFalse();
});

it('authorizes questionnaire documents under the questionnaire permission namespace', function () {
    $user = createUserWithPermissions(['questionnaire.documents.download']);
    $questionnaire = Questionnaire::create([]);

    expect($this->documentAuthorization->authorize(
        $user,
        DocumentAction::Download,
        DocumentAuthorizationContext::forOwner($questionnaire),
    ))->toBeTrue();
});

it('intersects cv capabilities with the authorization decision for authenticated actors', function () {
    $cv = Cv::create([]);

    $granted = createUserWithPermissions(['cv.documents.view']);
    $ungranted = createUserWithPermissions(['employee.documents.view']);

    expect($this->capabilities->forEntity($granted, $cv)['view'])->toBeTrue();
    expect($this->capabilities->forEntity($ungranted, $cv)['view'])->toBeFalse();
});

it('keeps the business capability for grant-based (anonymous) flows', function () {
    $cv = Cv::create([]);

    $capabilities = $this->capabilities->forEntity(null, $cv);

    expect($capabilities['view'])->toBeTrue();
    expect($capabilities['download'])->toBeTrue();
    expect($capabilities['replace'])->toBeFalse();
});
