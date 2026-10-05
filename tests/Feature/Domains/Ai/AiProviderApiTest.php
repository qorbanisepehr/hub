<?php

use App\Domains\Ai\Enums\AiCapability;
use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Ai\Services\AiConnectionTester;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiDefaultProvider;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Agent;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Seeded through the store rather than a factory.
 *
 * There is no provider factory any more and there should not be one: a factory
 * that inserts a document has to reach past the store to do it, and then it is
 * seeding rows the application itself cannot write. Going through the store
 * means every row in these tests arrived the way a real one does, typed and
 * validated.
 */
function stored(): SystemSettingsRepositoryInterface
{
    return app(SystemSettingsRepositoryInterface::class);
}

function storeProvider(
    string $name = 'local',
    AiDriver $driver = AiDriver::Ollama,
    ?string $apiKey = null,
    array $models = [],
    ?string $defaultModel = null,
): AiProvider {
    $provider = AiProvider::make($name, $driver)
        ->withApiKey($apiKey)
        ->withModels($models, $defaultModel);

    stored()->put($provider);

    return $provider;
}

function storeAgent(string $key = 'board-meeting.summary', ?string $provider = null, ?string $model = null): AiAgent
{
    $agent = new AiAgent($key, 'Moulid khalase', Agent::class, $provider, $model);

    stored()->put($agent);

    return $agent;
}

describe('GET /api/ai/providers', function () {
    it('forbids a user without ai.provider.view', function () {
        actingAs(User::factory()->create())->getJson('/api/ai/providers')->assertForbidden();
    });

    it('lists providers with their models', function () {
        storeProvider('local', models: ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)]);

        $response = actingAs(createUserWithPermissions(['ai.provider.view']))->getJson('/api/ai/providers')->assertOk();

        expect($response->json('data.0.name'))->toBe('local')
            ->and($response->json('data.0.models.0.model_name'))->toBe('gpt-oss:20b');
    });

    // Every other AI list depends on the paginator surviving the mapping from
    // documents to resources. `setCollection()` is typed to a Collection and
    // `meta`/`links` only exist on a paginated response, so this is the one
    // assertion that would have caught that wiring being wrong.
    it('answers with the pagination envelope the client tables read', function () {
        storeProvider('alpha');
        storeProvider('zed');

        $response = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/providers?per_page=1&page=2')
            ->assertOk();

        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.name'))->toBe('zed')
            ->and($response->json('meta.total'))->toBe(2)
            ->and($response->json('meta.current_page'))->toBe(2)
            ->and($response->json('meta.last_page'))->toBe(2)
            ->and($response->json('links'))->not->toBeNull();
    });

    it('never returns the api key, only whether one is stored', function () {
        storeProvider('cloud', AiDriver::OpenAI, 'sk-secret-value');

        $response = actingAs(createUserWithPermissions(['ai.provider.view']))->getJson('/api/ai/providers')->assertOk();

        // Not just absent from `data` - absent from the whole body. A key that
        // leaked into a `meta` envelope or an error field would be just as bad.
        expect($response->json('data.0'))->not->toHaveKey('api_key')
            ->and($response->json('data.0.has_api_key'))->toBeTrue()
            ->and($response->getContent())->not->toContain('sk-secret-value');
    });

    it('is readable by a viewer who holds no manage permission', function () {
        storeProvider();

        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)->getJson('/api/ai/providers')->assertOk();

        actingAs($user)
            ->postJson('/api/ai/providers', ['name' => 'new', 'driver' => 'ollama'])
            ->assertForbidden();
    });

    it('filters by a document field, which is not a column', function () {
        storeProvider('production-gateway');
        storeProvider('laptop');

        $response = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/providers?filter=production')
            ->assertOk();

        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.name'))->toBe('production-gateway')
            ->and($response->json('meta.total'))->toBe(1);
    });

    it('searches inside a url, which the stored text would otherwise hide', function () {
        $user = createUserWithPermissions(['ai.provider.view']);

        stored()->put(AiProvider::make('tunnelled', AiDriver::Ollama)->withBaseUrl('https://ollama.internal:11434'));
        storeProvider('plain');

        // `json_encode` writes `https:\/\/…`, so matching the raw column would
        // find nothing here, and `jsonb LIKE text` is not a legal operator on
        // Postgres at all. Only the extracted value can answer this.
        expect(actingAs($user)->getJson('/api/ai/providers?filter=//ollama')->assertOk()->json('data.0.name'))
            ->toBe('tunnelled');
    });

    it('sorts by a document field, with the key settling ties', function () {
        $user = createUserWithPermissions(['ai.provider.view']);

        storeProvider('zed');
        storeProvider('alpha');
        storeProvider('cloud', AiDriver::OpenAI, 'sk-1');

        // Both ollama providers tie on `driver`, and the tie has to break the
        // same way on every request — otherwise paging through a tied sort can
        // show the same row twice and skip another.
        $names = actingAs($user)
            ->getJson('/api/ai/providers?sort=driver&order=asc')
            ->assertOk()
            ->json('data.*.name');

        expect($names)->toBe(['alpha', 'zed', 'cloud']);
    });

    it('falls back to the default order for a column it does not have', function () {
        $user = createUserWithPermissions(['ai.provider.view']);

        storeProvider('alpha');
        storeProvider('zed');

        // `created_at` is gone with the columns. A client still offering that
        // header gets the default order rather than an error or a wrong answer.
        expect(actingAs($user)->getJson('/api/ai/providers?sort=created_at')->assertOk()->json('data.*.name'))
            ->toBe(['alpha', 'zed']);
    });

    it('marks which provider is the default', function () {
        $user = createUserWithPermissions(['ai.provider.view']);

        storeProvider('alpha');
        storeProvider('zed');
        stored()->put(AiDefaultProvider::make('zed'));

        $defaults = actingAs($user)->getJson('/api/ai/providers')->assertOk()->json('data.*.is_default');

        expect($defaults)->toBe([false, true]);
    });
});

describe('POST /api/ai/providers', function () {
    it('creates a provider with the manage permission', function () {
        $response = actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers', ['name' => 'local', 'driver' => 'ollama', 'base_url' => 'http://localhost:11434'])
            ->assertCreated();

        expect($response->json('data.name'))->toBe('local')
            ->and($response->json('data.driver'))->toBe('ollama')
            ->and(stored()->exists(AiProvider::class, 'providers/local'))->toBeTrue();
    });

    it('accepts an ollama provider with no api key at all', function () {
        $response = actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers', ['name' => 'local', 'driver' => 'ollama'])
            ->assertCreated();

        expect($response->json('data.has_api_key'))->toBeFalse()
            // With the opening quote, so this cannot match inside the
            // `has_api_key` flag that is *meant* to be there.
            ->and($response->getContent())->not->toContain('"api_key":');
    });

    it('requires an api key for a hosted driver', function () {
        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers', ['name' => 'cloud', 'driver' => 'openai'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('api_key');
    });

    it('rejects a duplicate name', function () {
        storeProvider('local');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers', ['name' => 'local', 'driver' => 'ollama'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    it('rejects an unknown driver', function () {
        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers', ['name' => 'local', 'driver' => 'teapot'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('driver');
    });

    it('forbids a viewer', function () {
        actingAs(createUserWithPermissions(['ai.provider.view']))
            ->postJson('/api/ai/providers', ['name' => 'new', 'driver' => 'ollama'])
            ->assertForbidden();
    });
});

describe('PUT /api/ai/providers/{provider}', function () {
    it('keeps the stored key when the request omits one', function () {
        storeProvider('local', AiDriver::OpenAI, 'sk-original');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/local', ['base_url' => 'http://ollama.internal:11434'])
            ->assertOk();

        expect(stored()->get(AiProvider::class, 'providers/local')?->apiKey())->toBe('sk-original');
    });

    it('replaces the key when one is sent', function () {
        storeProvider('local', AiDriver::OpenAI, 'sk-original');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/local', ['api_key' => 'sk-replaced'])
            ->assertOk();

        expect(stored()->get(AiProvider::class, 'providers/local')?->apiKey())->toBe('sk-replaced');
    });

    it('clears a stored key only when asked explicitly', function () {
        storeProvider('local', AiDriver::OpenAI, 'sk-original');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/local', ['clear_api_key' => true])
            ->assertOk();

        expect(stored()->get(AiProvider::class, 'providers/local')?->apiKey())->toBeNull();
    });

    it('keeps the key when the flag and a new key are both sent', function () {
        storeProvider('local', AiDriver::OpenAI, 'sk-original');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/local', ['api_key' => 'sk-new', 'clear_api_key' => true])
            ->assertOk();

        expect(stored()->get(AiProvider::class, 'providers/local')?->apiKey())->toBeNull();
    });

    it('never renames a provider', function () {
        storeProvider('local');

        // The name is the key the setting is stored under and the segment the
        // URL addresses, so a rename is a move rather than an edit. The route
        // does not accept it and the request does not either.
        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/local', ['name' => 'renamed'])
            ->assertOk();

        expect(stored()->exists(AiProvider::class, 'providers/local'))->toBeTrue()
            ->and(stored()->exists(AiProvider::class, 'providers/renamed'))->toBeFalse();
    });

    it('ignores a driver the client echoed back, and reports the real one', function () {
        storeProvider('local', AiDriver::Ollama);

        // The dialog sends the row it was given, so `driver` is present on every
        // edit. Refusing it would make editing a base URL impossible; silently
        // keeping it is what a frozen field has to mean, and the response still
        // carries the truth so the form re-renders with the real driver.
        $response = actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/local', ['driver' => 'openai', 'base_url' => 'http://x.internal'])
            ->assertOk();

        expect($response->json('data.driver'))->toBe('ollama')
            ->and($response->json('data.base_url'))->toBe('http://x.internal')
            ->and(stored()->get(AiProvider::class, 'providers/local')?->driver())->toBe(AiDriver::Ollama);
    });

    it('answers 404 for a provider that is not there', function () {
        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/never-stored', ['base_url' => 'http://x.internal'])
            ->assertNotFound();
    });

    it('forbids a viewer', function () {
        storeProvider();

        actingAs(createUserWithPermissions(['ai.provider.view']))
            ->putJson('/api/ai/providers/local', ['base_url' => 'http://x.internal'])
            ->assertForbidden();
    });
});

describe('POST /api/ai/providers/{provider}/default', function () {
    it('promotes a provider and demotes the previous default', function () {
        storeProvider('alpha');
        storeProvider('zed');
        stored()->put(AiDefaultProvider::make('alpha'));

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/zed/default')
            ->assertOk();

        expect(stored()->get(AiDefaultProvider::class, 'default_provider')?->provider())
            ->toBe('zed');
    });

    it('forbids a viewer', function () {
        storeProvider();

        actingAs(createUserWithPermissions(['ai.provider.view']))
            ->postJson('/api/ai/providers/local/default')
            ->assertForbidden();
    });
});

describe('DELETE /api/ai/providers/{provider}', function () {
    it('deletes a provider with its models', function () {
        storeProvider('local', models: ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)]);

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->deleteJson('/api/ai/providers/local')
            ->assertOk();

        expect(stored()->exists(AiProvider::class, 'providers/local'))->toBeFalse();
    });

    it('forbids a viewer', function () {
        storeProvider();

        actingAs(createUserWithPermissions(['ai.provider.view']))
            ->deleteJson('/api/ai/providers/local')
            ->assertForbidden();
    });
});

describe('provider models', function () {
    it('adds a model under its provider', function () {
        storeProvider('local');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/models', ['model_name' => 'gpt-oss:20b'])
            ->assertCreated();

        expect(stored()->get(AiProvider::class, 'providers/local')?->hasModel('gpt-oss:20b'))->toBeTrue();
    });

    it('rejects a model name the gateway would never publish', function () {
        storeProvider('local');

        // Real model names carry colons and dots (`llama3:8b`, `qwen2.5:14b`),
        // so the model pattern is looser than the provider one.
        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/models', ['model_name' => 'llama3:8b'])
            ->assertCreated();
    });

    it('rejects a capability outside the enum', function () {
        storeProvider('local');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/models', ['model_name' => 'x', 'capabilities' => ['telepathy']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capabilities.0');
    });

    it('rejects the same model name twice on one provider', function () {
        storeProvider('local', models: ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)]);

        // jsonb would quietly keep the last of a duplicated key, so without this
        // check the first entry would silently become the second.
        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/models', ['model_name' => 'gpt-oss:20b'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('model_name');
    });

    it('allows the same model name on two providers', function () {
        storeProvider('alpha');
        storeProvider('zed');

        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)->postJson('/api/ai/providers/alpha/models', ['model_name' => 'gpt-4o-mini'])->assertCreated();
        actingAs($user)->postJson('/api/ai/providers/zed/models', ['model_name' => 'gpt-4o-mini'])->assertCreated();

        expect(stored()->get(AiProvider::class, 'providers/alpha')?->hasModel('gpt-4o-mini'))->toBeTrue()
            ->and(stored()->get(AiProvider::class, 'providers/zed')?->hasModel('gpt-4o-mini'))->toBeTrue();
    });

    it('renames a model without colliding with itself', function () {
        storeProvider('local', models: ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)]);

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/local/models/gpt-oss:20b', ['model_name' => 'gpt-oss:120b'])
            ->assertOk();

        $provider = stored()->get(AiProvider::class, 'providers/local');

        expect($provider?->hasModel('gpt-oss:20b'))->toBeFalse()
            ->and($provider?->hasModel('gpt-oss:120b'))->toBeTrue();
    });

    it('moves the default pointer with a rename', function () {
        storeProvider('local', models: ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)], defaultModel: 'gpt-oss:20b');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/local/models/gpt-oss:20b', ['model_name' => 'gpt-oss:120b'])
            ->assertOk();

        // The pointer names a model, so leaving it on the old name would make
        // the stored document refuse to load at all.
        expect(stored()->get(AiProvider::class, 'providers/local')?->defaultModel())->toBe('gpt-oss:120b');
    });

    it('refuses to edit a model through the wrong provider', function () {
        storeProvider('alpha');
        storeProvider('zed', models: ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)]);

        // 404, not 422: the route addresses a model *under a provider*, and
        // `alpha` serves no such model, so the thing being addressed is not
        // there. A 422 would be right if the provider were a body field.
        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/providers/alpha/models/gpt-oss:20b', ['model_name' => 'stolen'])
            ->assertNotFound();

        expect(stored()->get(AiProvider::class, 'providers/zed')?->hasModel('gpt-oss:20b'))->toBeTrue();
    });

    it('unbinds an agent that was using the model being deleted', function () {
        storeProvider('local', models: ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)]);
        storeAgent('board-meeting.summary', 'local', 'gpt-oss:20b');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->deleteJson('/api/ai/providers/local/models/gpt-oss:20b')
            ->assertOk();

        // What the foreign keys used to do for free. A binding left naming a
        // deleted model would resolve to nothing at run time and fail there.
        expect(stored()->get(AiAgent::class, 'agents/board-meeting.summary')?->model())->toBeNull();
    });

    it('deletes a model', function () {
        storeProvider('local', models: ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)]);

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->deleteJson('/api/ai/providers/local/models/gpt-oss:20b')
            ->assertOk();

        expect(stored()->get(AiProvider::class, 'providers/local')?->hasModel('gpt-oss:20b'))->toBeFalse();
    });

    it('forbids a viewer', function () {
        storeProvider();

        actingAs(createUserWithPermissions(['ai.provider.view']))
            ->postJson('/api/ai/providers/local/models', ['model_name' => 'x'])
            ->assertForbidden();
    });
});

describe('GET /api/ai/models', function () {
    it('lists every pickable model across providers, with its provider', function () {
        $user = createUserWithPermissions(['ai.provider.view']);

        storeProvider('alpha', models: [
            'gpt-oss:20b' => new AiModel('gpt-oss:20b', 'GPT OSS', [], true),
            'retired:1b' => new AiModel('retired:1b', null, [], false),
        ]);
        storeProvider('zed', models: ['qwen2.5:14b' => new AiModel('qwen2.5:14b', null, [], true)]);

        $models = actingAs($user)->getJson('/api/ai/models')->assertOk()->json('data');

        // Only the active ones, and only on an active provider: the picker has
        // to offer exactly what the resolver will accept.
        expect($models)->toHaveCount(2)
            ->and($models[0])->toMatchArray(['provider_name' => 'alpha', 'model_name' => 'gpt-oss:20b', 'display_label' => 'GPT OSS'])
            ->and($models[1])->toMatchArray(['provider_name' => 'zed', 'model_name' => 'qwen2.5:14b']);
    });

    it('offers nothing behind a deactivated provider', function () {
        // `asActive()` returns a new provider, so the deactivated one has to be
        // the one that is stored — writing the original would leave it live.
        stored()->put(AiProvider::make('alpha', AiDriver::Ollama)->asActive(false)->withModels(
            ['gpt-oss:20b' => new AiModel('gpt-oss:20b', null, [], true)],
            null,
        ));

        expect(actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/models')
            ->assertOk()
            ->json('data'))->toBe([]);
    });

    it('answers with the pagination envelope too', function () {
        $user = createUserWithPermissions(['ai.provider.view']);

        storeProvider('alpha', models: [
            'a:1b' => new AiModel('a:1b', null, [], true),
            'b:1b' => new AiModel('b:1b', null, [], true),
        ]);

        $response = actingAs($user)->getJson('/api/ai/models?per_page=1&page=2')->assertOk();

        // Hand-built paginator, so this is the assertion that keeps it shaped
        // like the query-backed lists rather than like a bare array.
        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('meta.total'))->toBe(2)
            ->and($response->json('meta.last_page'))->toBe(2);
    });

    it('never exposes a credential through the picker', function () {
        storeProvider('alpha', AiDriver::OpenAI, 'sk-secret-value', [
            'gpt-4o-mini' => new AiModel('gpt-4o-mini', null, [], true),
        ]);

        $response = actingAs(createUserWithPermissions(['ai.provider.view']))->getJson('/api/ai/models')->assertOk();

        expect($response->json('data.0'))->not->toHaveKeys(['base_url', 'has_api_key', 'headers', 'api_key'])
            ->and($response->getContent())->not->toContain('sk-secret-value');
    });
});

describe('GET /api/ai/agents', function () {
    it('forbids a user without ai.provider.view', function () {
        actingAs(User::factory()->create())->getJson('/api/ai/agents')->assertForbidden();
    });

    it('lists agents with what they are bound to', function () {
        storeProvider('local');
        storeAgent('board-meeting.summary', 'local', 'gemma3:latest');

        $response = actingAs(createUserWithPermissions(['ai.provider.view']))->getJson('/api/ai/agents')->assertOk();

        expect($response->json('data.0'))->toMatchArray([
            'key' => 'board-meeting.summary',
            'label' => 'Moulid khalase',
            'provider' => 'local',
            'model' => 'gemma3:latest',
            'is_bound' => true,
        ]);
    });

    it('sends the binding as names, with no row ids to disagree with them', function () {
        storeProvider('local');
        storeAgent('board-meeting.summary', 'local', 'gemma3:latest');

        $agent = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/agents')
            ->assertOk()
            ->json('data.0');

        expect($agent)->not->toHaveKeys(['id', 'provider_id', 'model_id']);
    });

    it('answers with the pagination envelope', function () {
        storeAgent('board-meeting.summary');
        storeAgent('cv.rewrite');

        $response = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/agents?per_page=1&page=2')
            ->assertOk();

        expect($response->json('data.0.key'))->toBe('cv.rewrite')
            ->and($response->json('meta.total'))->toBe(2)
            ->and($response->json('links'))->not->toBeNull();
    });

    it('reports an unbound agent as not bound', function () {
        storeAgent('board-meeting.summary');

        $agent = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/agents')
            ->assertOk()
            ->json('data.0');

        expect($agent['is_bound'])->toBeFalse()
            ->and($agent['provider'])->toBeNull()
            ->and($agent['model'])->toBeNull();
    });
});

describe('PUT /api/ai/agents/{agent}', function () {
    it('binds an agent to a provider and model', function () {
        storeProvider('local', models: ['gemma3:latest' => new AiModel('gemma3:latest', null, [], true)]);
        storeAgent();

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/agents/board-meeting.summary', ['provider' => 'local', 'model' => 'gemma3:latest'])
            ->assertOk();

        $agent = stored()->get(AiAgent::class, 'agents/board-meeting.summary');

        expect($agent?->provider())->toBe('local')
            ->and($agent?->model())->toBe('gemma3:latest');
    });

    it('clears a binding back to inheriting the default', function () {
        storeProvider('local');
        storeAgent('board-meeting.summary', 'local', 'gemma3:latest');

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/agents/board-meeting.summary', ['provider' => null, 'model' => null])
            ->assertOk();

        $agent = stored()->get(AiAgent::class, 'agents/board-meeting.summary');

        expect($agent?->provider())->toBeNull()
            ->and($agent?->model())->toBeNull()
            ->and($agent?->isBound())->toBeFalse();
    });

    it('rejects a model belonging to another provider', function () {
        storeProvider('alpha', models: ['shared:1b' => new AiModel('shared:1b', null, [], true)]);
        storeProvider('zed');
        storeAgent();

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/agents/board-meeting.summary', ['provider' => 'zed', 'model' => 'shared:1b'])
            ->assertUnprocessable();
    });

    it('rejects a provider that is not there', function () {
        storeAgent();

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->putJson('/api/ai/agents/board-meeting.summary', ['provider' => 'never-stored'])
            ->assertUnprocessable();
    });

    it('leaves the agent class and key alone', function () {
        $user = createUserWithPermissions(['ai.provider.manage']);

        storeProvider('local');
        storeAgent();

        actingAs($user)->putJson('/api/ai/agents/board-meeting.summary', [
            'key' => 'hijacked',
            'agent_class' => 'App\\Nope',
            'provider' => 'local',
        ])->assertOk();

        $agent = stored()->get(AiAgent::class, 'agents/board-meeting.summary');

        expect($agent?->agentKey())->toBe('board-meeting.summary')
            ->and($agent?->agentClass())->toBe(Agent::class)
            ->and(stored()->exists(AiAgent::class, 'agents/hijacked'))->toBeFalse();
    });

    it('forbids a viewer', function () {
        storeAgent();

        actingAs(createUserWithPermissions(['ai.provider.view']))
            ->putJson('/api/ai/agents/board-meeting.summary', ['provider' => 'local'])
            ->assertForbidden();
    });
});

describe('POST /api/ai/providers/{provider}/test', function () {
    it('reports a reachable provider', function () {
        storeProvider('local');

        $this->partialMock(AiConnectionTester::class)
            ->shouldReceive('test')
            ->once()
            ->andReturn(['ok' => true, 'model' => 'gemma3:latest', 'reply' => 'ok', 'tokens' => 3]);

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/test')
            ->assertOk()
            ->assertJsonPath('ok', true);
    });

    it('passes the model it was asked to test through', function () {
        storeProvider('local', models: ['registered:1b' => new AiModel('registered:1b', null, [], true)]);

        // The registry already serves a model, and the point of the box is to
        // try a *different* one - usually one about to be saved.
        $this->partialMock(AiConnectionTester::class)
            ->shouldReceive('test')
            ->once()
            ->with(Mockery::on(fn (AiProvider $p): bool => $p->name() === 'local'), 'unregistered:7b')
            ->andReturn(['ok' => true, 'model' => 'unregistered:7b', 'reply' => 'ok', 'tokens' => 3]);

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/test', ['model' => 'unregistered:7b'])
            ->assertOk();
    });

    it('answers 422 with the reason when the provider cannot be reached', function () {
        storeProvider('local');

        $this->partialMock(AiConnectionTester::class)
            ->shouldReceive('test')
            ->once()
            ->andThrow(AiProviderUnreachable::because(
                stored()->get(AiProvider::class, 'providers/local'),
                new RuntimeException('401 Unauthorized'),
            ));

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/test')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'AI provider [local] could not be reached: 401 Unauthorized');
    });

    it('never puts the credential in the failure it reports', function () {
        storeProvider('local', AiDriver::OpenAI, 'sk-secret-value');

        $this->partialMock(AiConnectionTester::class)
            ->shouldReceive('test')
            ->once()
            ->andThrow(AiProviderUnreachable::because(
                stored()->get(AiProvider::class, 'providers/local'),
                new RuntimeException('401 Unauthorized'),
            ));

        $response = actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/test')
            ->assertUnprocessable();

        expect($response->getContent())->not->toContain('sk-secret-value');
    });

    it('forbids a viewer', function () {
        storeProvider();

        actingAs(createUserWithPermissions(['ai.provider.view']))
            ->postJson('/api/ai/providers/local/test')
            ->assertForbidden();
    });
});

describe('the capability vocabulary', function () {
    it('exposes every capability the enum defines', function () {
        storeProvider('local');

        $capabilities = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/providers')
            ->assertOk();

        $posted = actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers/local/models', [
                'model_name' => 'everything',
                'capabilities' => array_map(fn (AiCapability $c): string => $c->value, AiCapability::cases()),
            ])
            ->assertCreated();

        expect($posted->json('data.capabilities'))->toHaveCount(count(AiCapability::cases()))
            ->and($capabilities->status())->toBe(200);
    });

    it('accepts a provider for every driver the enum offers', function () {
        $user = createUserWithPermissions(['ai.provider.manage']);

        foreach (AiDriver::cases() as $index => $driver) {
            actingAs($user)
                ->postJson('/api/ai/providers', [
                    'name' => 'driver-'.$index,
                    'driver' => $driver->value,
                    'api_key' => 'sk-'.$index,
                ])
                ->assertCreated();
        }

        expect(stored()->all(AiProvider::class))->toHaveCount(count(AiDriver::cases()));
    });
});
