<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Attributes\UseSmartestModel;
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

test('no domain agent pins a model or a provider', function () {
    /*
     * D28 says the model comes from the registry row and nothing else, so
     * that swapping models is a click in Settings rather than a deploy. That
     * is easy to honour in review and easy to break by accident: pinning one
     * model "just for this agent" is the most likely way a domain quietly
     * opts out of the registry, and it looks reasonable at the time.
     *
     * These four attributes are the only ways the SDK lets a class do it.
     * Pest's `arch()->not->toUse()` catches a class named in code but does
     * not look inside attributes — verified by adding one and watching the
     * suite stay green — so this reads them off the classes instead.
     */
    $pinning = [
        Model::class,
        Provider::class,
        UseCheapestModel::class,
        UseSmartestModel::class,
    ];

    $offenders = [];

    foreach (File::allFiles(app_path('Domains')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $class = 'App\\'.str_replace(
            [DIRECTORY_SEPARATOR, '.php'],
            ['\\', ''],
            Str::after($file->getRealPath(), base_path('app').DIRECTORY_SEPARATOR),
        );

        if (! class_exists($class)) {
            continue;
        }

        foreach ((new ReflectionClass($class))->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), $pinning, true)) {
                $offenders[] = $class.' → '.$attribute->getName();
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('a provider can never serialize its api key', function () {
    $provider = AiProvider::make('cloud', AiDriver::OpenAI)->withApiKey('sk-should-never-leave-the-server');

    /*
     * The guarantee used to be an Eloquent `$hidden`, which the resource
     * relied on. A setting document has no such column list, so the guarantee
     * is structural instead: the credential is in `toSecrets()` and the public
     * half is `toValue()`. Every read path goes through one of the two, so a
     * provider that is serialized for the outside world can only reach the
     * first - there is no key in `toValue()` that could hold it by accident.
     */
    expect($provider->toValue())->not->toHaveKey('api_key')
        ->and(json_encode($provider->toValue()))->not->toContain('sk-should-never-leave-the-server')
        ->and($provider->toSecrets())->toBe(['api_key' => 'sk-should-never-leave-the-server'])
        ->and($provider->hasCredential())->toBeTrue();
});

test('the public half of a provider round-trips without the secret', function () {
    $provider = AiProvider::make('cloud', AiDriver::OpenAI)
        ->withApiKey('sk-secret')
        ->withModels(['gpt-4o-mini' => new AiModel('gpt-4o-mini', 'GPT-4o mini', [], true)], 'gpt-4o-mini');

    $restored = AiProvider::fromStored($provider->key(), $provider->toValue(), $provider->toSecrets());

    // Rebuilding from the stored halves must give the same provider back, and
    // the credential must arrive through the secrets channel alone.
    expect($restored->name())->toBe('cloud')
        ->and($restored->apiKey())->toBe('sk-secret')
        ->and($restored->activeModelNames())->toBe(['gpt-4o-mini'])
        ->and($restored->toValue())->not->toHaveKey('api_key');
});
