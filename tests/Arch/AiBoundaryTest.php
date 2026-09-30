<?php

use App\Domains\Ai\Models\AiAgent;
use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The Ai domain owns credentials and the agent registry; it owns no agent.
 *
 * D28 puts an agent class in the *consuming* domain, so `App\Domains\BoardMeeting\Ai`
 * is where a summary agent belongs, and the Ai domain's job is to run whatever
 * class a registry row names. That direction is the whole reason another
 * domain can depend on Ai without Ai knowing anything about it (D19), and it
 * is easy to break by accident: an agent that "logically" belongs next to the
 * runner is the first step toward the Ai domain depending on the thing it
 * serves.
 *
 * The two things that must stay true:
 *   1. Ai never imports a business domain, so it cannot know its callers;
 *   2. no agent class lives inside the Ai domain, so the runner has nothing
 *      shipped that would tempt a caller into depending on Ai for an agent.
 */

arch('the ai domain never depends on a business domain')
    ->expect('App\Domains\Ai')
    ->not->toUse([
        'App\Domains\BoardMeeting',
        'App\Domains\Employee',
        'App\Domains\Document',
        'App\Domains\Questionnaire',
        'App\Domains\Cv',
    ]);

arch('the ai domain never depends on integrations, ingest, or the external contracts')
    ->expect('App\Domains\Ai')
    ->not->toUse([
        'App\Integrations',
        'App\Domains\Ingest',
        'App\Contracts\External',
    ]);

arch('the ai domain keeps the rbac implementation out')
    ->expect('App\Domains\Ai')
    ->not->toUse(['App\Domains\Authorization\Services', 'App\Services\AuthorizationService']);

test('the ai domain ships no agent class of its own', function () {
    // A registry without agents is a supported state (D24), so this is an
    // emptiness assertion, not a "one agent exists" assertion.
    expect(glob(app_path('Domains/Ai/Agents')))->toBe([]);
});

test('the registry models expose nothing beyond their own tables', function () {
    // The registry is a leaf too: its relations are the three AI tables and
    // nothing else, so no association can quietly pull a business domain in.
    foreach ([AiProvider::class, AiModel::class, AiAgent::class] as $model) {
        $relations = array_keys((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC));

        expect($relations)->not->toContain('employees', 'documents', 'meetings');
    }
});

test('a provider row can never serialize its api key', function () {
    $provider = new AiProvider(['api_key' => 'sk-should-never-leave-the-server']);

    // `hidden` is the guarantee the resource relies on; assert it here so a
    // future serializer that forgets to hide it fails at the model, not in a
    // browser.
    expect($provider->toArray())->not->toHaveKey('api_key')
        ->and($provider->toJson())->not->toContain('sk-should-never-leave-the-server');
});
