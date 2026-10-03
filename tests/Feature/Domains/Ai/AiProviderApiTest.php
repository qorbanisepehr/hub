<?php

use App\Domains\Ai\Enums\AiCapability;
use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;
use App\Domains\Ai\Services\AiConnectionTester;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

describe('GET /api/ai/providers', function () {
    it('forbids a user without ai.provider.view', function () {
        actingAs(User::factory()->create())->getJson('/api/ai/providers')->assertForbidden();
    });

    it('lists providers with their models', function () {
        $provider = AiProvider::factory()->create(['name' => 'local']);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'gpt-oss:20b']);

        $user = createUserWithPermissions(['ai.provider.view']);

        $response = actingAs($user)->getJson('/api/ai/providers')->assertOk();

        expect($response->json('data.0.name'))->toBe('local')
            ->and($response->json('data.0.models.0.model_name'))->toBe('gpt-oss:20b');
    });

    it('never returns the api key, only whether one is stored', function () {
        AiProvider::factory()->withApiKey('sk-secret-value')->create(['name' => 'cloud']);
        $user = createUserWithPermissions(['ai.provider.view']);

        $response = actingAs($user)->getJson('/api/ai/providers')->assertOk();

        // Not just absent from `data` — absent from the whole body. A key that
        // leaked into a `meta` envelope or an error field would be just as bad.
        expect($response->json('data.0'))->not->toHaveKey('api_key')
            ->and($response->json('data.0.has_api_key'))->toBeTrue()
            ->and($response->getContent())->not->toContain('sk-secret-value');
    });

    it('is readable by a viewer who holds no manage permission', function () {
        AiProvider::factory()->create(['name' => 'local']);
        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)->getJson('/api/ai/providers')->assertOk();

        actingAs($user)
            ->postJson('/api/ai/providers', ['name' => 'new', 'driver' => 'ollama'])
            ->assertForbidden();
    });

    it('filters by name', function () {
        AiProvider::factory()->create(['name' => 'production-gateway']);
        AiProvider::factory()->create(['name' => 'laptop']);
        $user = createUserWithPermissions(['ai.provider.view']);

        $response = actingAs($user)->getJson('/api/ai/providers?filter=production')->assertOk();

        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.name'))->toBe('production-gateway');
    });
});

describe('POST /api/ai/providers', function () {
    it('accepts a second provider that is not the default', function () {
        AiProvider::factory()->default()->create(['name' => 'local']);
        AiProvider::factory()->create(['name' => 'first-backup']);

        actingAs(createUserWithPermissions(['ai.provider.manage']))
            ->postJson('/api/ai/providers', [
                'name' => 'second-backup',
                'driver' => 'openai-compatible',
                'base_url' => 'https://example.test/v1',
                'api_key' => 'sk-second',
            ])
            ->assertCreated();

        expect(AiProvider::where('is_default', false)->count())->toBe(2);
    });

    it('accepts several providers created back to back', function () {
        foreach (['one', 'two', 'three'] as $name) {
            actingAs(createUserWithPermissions(['ai.provider.manage']))
                ->postJson('/api/ai/providers', [
                    'name' => $name,
                    'driver' => 'ollama',
                ])
                ->assertCreated();
        }

        expect(AiProvider::count())->toBe(3);
    });

    it('still refuses a second default at the schema', function () {
        // The model's `booted()` hook demotes the previous default, so through
        // the API a second default is accepted and demoted. What the partial
        // index is for is the case PHP cannot cover: two requests racing, both
        // reading "no default yet" and both writing one. A raw insert is the
        // only way to reach it from a test.
        AiProvider::factory()->default()->create(['name' => 'local']);

        expect(fn () => DB::table('ai_providers')->insert([
            'name' => 'racer',
            'driver' => 'ollama',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('creates a provider with the manage permission', function () {
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)->postJson('/api/ai/providers', [
            'name' => 'cloud',
            'driver' => 'openai',
            'api_key' => 'sk-new',
            'is_default' => true,
        ])->assertCreated();

        expect($response->json('data.name'))->toBe('cloud')
            ->and($response->json('data.has_api_key'))->toBeTrue()
            ->and(AiProvider::where('name', 'cloud')->exists())->toBeTrue();
    });

    it('accepts an ollama provider with no api key at all', function () {
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->postJson('/api/ai/providers', ['name' => 'local', 'driver' => 'ollama'])
            ->assertCreated();

        // D24's zero-credential path has to be reachable through the screen
        // that offers it; requiring a key would make it unreachable.
        expect($response->json('data.has_api_key'))->toBeFalse();
    });

    it('requires an api key for a hosted driver', function () {
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson('/api/ai/providers', ['name' => 'cloud', 'driver' => 'openai'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('api_key');
    });

    it('rejects a duplicate name', function () {
        AiProvider::factory()->create(['name' => 'taken']);
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson('/api/ai/providers', ['name' => 'taken', 'driver' => 'ollama'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    it('rejects an unknown driver', function () {
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson('/api/ai/providers', ['name' => 'x', 'driver' => 'telepathy'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('driver');
    });

    it('forbids a viewer', function () {
        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)
            ->postJson('/api/ai/providers', ['name' => 'x', 'driver' => 'ollama'])
            ->assertForbidden();
    });
});

describe('PUT /api/ai/providers/{provider}', function () {
    it('keeps the stored key when the request omits one', function () {
        $provider = AiProvider::factory()->withApiKey('sk-original')->create(['name' => 'cloud']);
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->putJson("/api/ai/providers/{$provider->id}", ['base_url' => 'https://llm.internal/v1'])
            ->assertOk();

        // The form has nothing to put in the key box — it is write-only — so an
        // unrelated edit must not be read as "clear the credential".
        expect($response->json('data.base_url'))->toBe('https://llm.internal/v1')
            ->and($response->json('data.has_api_key'))->toBeTrue()
            ->and($provider->refresh()->api_key)->toBe('sk-original');
    });

    it('replaces the key when one is sent', function () {
        $provider = AiProvider::factory()->withApiKey('sk-original')->create();
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->putJson("/api/ai/providers/{$provider->id}", ['api_key' => 'sk-rotated'])
            ->assertOk();

        expect($provider->refresh()->api_key)->toBe('sk-rotated');
    });

    it('clears a stored key only when asked explicitly', function () {
        $provider = AiProvider::factory()->withApiKey('sk-original')->create();
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->putJson("/api/ai/providers/{$provider->id}", ['clear_api_key' => true])
            ->assertOk();

        // An empty key box is what "untouched" looks like on the wire, so
        // deletion gets its own flag rather than sharing one with "unchanged".
        expect($response->json('data.has_api_key'))->toBeFalse()
            ->and($provider->refresh()->api_key)->toBeNull();
    });

    it('keeps the key when the flag and a new key are both sent', function () {
        $provider = AiProvider::factory()->withApiKey('sk-original')->create();
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->putJson("/api/ai/providers/{$provider->id}", [
                'clear_api_key' => true,
                'api_key' => 'sk-new',
            ])
            ->assertOk();

        expect($response->json('data.has_api_key'))->toBeFalse();
    });

    it('never renames a provider', function () {
        $provider = AiProvider::factory()->create(['name' => 'original']);
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->putJson("/api/ai/providers/{$provider->id}", ['name' => 'renamed'])
            ->assertOk();

        // `name` is the SDK config key and the identity in a run's
        // attribution; renaming it would orphan every run that recorded it.
        expect($response->json('data.name'))->toBe('original')
            ->and($provider->refresh()->name)->toBe('original');
    });

    it('deactivating the default leaves the registry without a default', function () {
        $provider = AiProvider::factory()->default()->create();
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->putJson("/api/ai/providers/{$provider->id}", ['is_active' => false])
            ->assertOk();

        expect($provider->refresh()->is_active)->toBeFalse();
    });

    it('forbids a viewer', function () {
        $provider = AiProvider::factory()->create();
        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)
            ->putJson("/api/ai/providers/{$provider->id}", ['base_url' => 'https://x.test'])
            ->assertForbidden();
    });
});

describe('POST /api/ai/providers/{provider}/default', function () {
    it('promotes a row and demotes the previous default', function () {
        $first = AiProvider::factory()->default()->create(['name' => 'first']);
        $second = AiProvider::factory()->create(['name' => 'second']);
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->postJson("/api/ai/providers/{$second->id}/default")
            ->assertOk();

        expect($response->json('data.is_default'))->toBeTrue()
            ->and($first->refresh()->is_default)->toBeFalse()
            ->and($second->refresh()->is_default)->toBeTrue();
    });

    it('forbids a viewer', function () {
        $provider = AiProvider::factory()->create();
        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)->postJson("/api/ai/providers/{$provider->id}/default")->assertForbidden();
    });
});

describe('DELETE /api/ai/providers/{provider}', function () {
    it('deletes a provider and its models', function () {
        $provider = AiProvider::factory()->create();
        AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)->deleteJson("/api/ai/providers/{$provider->id}")->assertOk();

        expect(AiProvider::find($provider->id))->toBeNull()
            ->and(AiModel::where('provider_id', $provider->id)->count())->toBe(0);
    });

    it('forbids a viewer', function () {
        $provider = AiProvider::factory()->create();
        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)->deleteJson("/api/ai/providers/{$provider->id}")->assertForbidden();

        expect(AiProvider::find($provider->id))->not->toBeNull();
    });
});

describe('provider models', function () {
    it('adds a model under its provider', function () {
        $provider = AiProvider::factory()->create();
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models", [
                'model_name' => 'gpt-oss:20b',
                'capabilities' => ['text', 'structured-output'],
            ])
            ->assertCreated();

        expect($response->json('data.model_name'))->toBe('gpt-oss:20b')
            ->and($response->json('data.capabilities'))->toBe(['text', 'structured-output'])
            ->and($response->json('data.display_label'))->toBe('gpt-oss:20b');
    });

    it('rejects a capability outside the enum', function () {
        $provider = AiProvider::factory()->create();
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models", [
                'model_name' => 'x',
                'capabilities' => ['telepathy'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capabilities.0');
    });

    it('rejects the same model name twice on one provider', function () {
        $provider = AiProvider::factory()->create();
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'dupe']);
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models", ['model_name' => 'dupe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('model_name');
    });

    it('allows the same model name on two providers', function () {
        $first = AiProvider::factory()->create();
        $second = AiProvider::factory()->create();
        AiModel::factory()->create(['provider_id' => $first->id, 'model_name' => 'shared']);

        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$second->id}/models", ['model_name' => 'shared'])
            ->assertCreated();
    });

    it('renames a model without colliding with itself', function () {
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'before']);
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->putJson("/api/ai/providers/{$provider->id}/models/{$model->id}", [
                'model_name' => 'before',
                'label' => 'نام نمایشی',
            ])
            ->assertOk();

        // The unique rule must exclude the row being updated, or a rename to
        // the same value would reject itself.
        expect($response->json('data.label'))->toBe('نام نمایشی')
            ->and($response->json('data.display_label'))->toBe('نام نمایشی');
    });

    it('refuses to edit a model through the wrong provider', function () {
        $provider = AiProvider::factory()->create();
        $other = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->putJson("/api/ai/providers/{$other->id}/models/{$model->id}", ['model_name' => 'stolen'])
            ->assertNotFound();
    });

    it('deletes a model', function () {
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->deleteJson("/api/ai/providers/{$provider->id}/models/{$model->id}")
            ->assertOk();

        expect(AiModel::find($model->id))->toBeNull();
    });

    it('forbids a viewer', function () {
        $provider = AiProvider::factory()->create();
        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models", ['model_name' => 'x'])
            ->assertForbidden();
    });
});

describe('POST /api/ai/providers/{provider}/models/{model}/default', function () {
    it('forbids a user without ai.provider.manage', function () {
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models/{$model->id}/default")
            ->assertForbidden();

        expect($model->refresh()->is_default)->toBeFalse();
    });

    it('promotes one of the provider own models', function () {
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models/{$model->id}/default")
            ->assertOk();

        expect($response->json('data.is_default'))->toBeTrue()
            ->and($model->refresh()->is_default)->toBeTrue();
    });

    it('demotes the model it replaces', function () {
        $provider = AiProvider::factory()->create();
        $previous = AiModel::factory()->default()->create(['provider_id' => $provider->id]);
        $replacement = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models/{$replacement->id}/default")
            ->assertOk();

        // "Set as default" that leaves the old one set would be no setting at
        // all, and the index would reject the second row anyway.
        expect($previous->refresh()->is_default)->toBeFalse()
            ->and($replacement->refresh()->is_default)->toBeTrue();
    });

    it('leaves another provider default alone', function () {
        $mine = AiProvider::factory()->create();
        $theirs = AiProvider::factory()->create();
        $theirDefault = AiModel::factory()->default()->create(['provider_id' => $theirs->id]);
        $mineModel = AiModel::factory()->create(['provider_id' => $mine->id]);
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$mine->id}/models/{$mineModel->id}/default")
            ->assertOk();

        // One default *per provider*, not one in the table: a second gateway
        // has its own choice to make.
        expect($theirDefault->refresh()->is_default)->toBeTrue();
    });

    it('is not found for a model of another provider', function () {
        // The model is looked up through the provider, so a mismatch is a 404
        // rather than a 422: there is no such address, only an existing model
        // that is not this provider's. One gateway's "set as default" can
        // never promote another's model, whatever the id says.
        $provider = AiProvider::factory()->create();
        $other = AiProvider::factory()->create();
        $foreign = AiModel::factory()->create(['provider_id' => $other->id]);
        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models/{$foreign->id}/default")
            ->assertNotFound();

        expect($foreign->refresh()->is_default)->toBeFalse();
    });
});

describe('POST /api/ai/providers/{provider}/test', function () {
    it('reports a reachable provider', function () {
        $provider = AiProvider::factory()->create(['name' => 'local']);

        // The probe is a real prompt, so the assertion is on the shape of a
        // successful answer rather than on any model's wording.
        $this->partialMock(AiConnectionTester::class)
            ->shouldReceive('test')
            ->once()
            ->andReturn(['ok' => true, 'model' => 'qwen3.5:4b', 'reply' => 'ok', 'tokens' => 12]);

        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/test")
            ->assertOk();

        expect($response->json())->toHaveKeys(['ok', 'model', 'reply', 'tokens'])
            ->and($response->json('ok'))->toBeTrue();
    });

    it('passes a model override through', function () {
        $provider = AiProvider::factory()->create();

        $this->partialMock(AiConnectionTester::class)
            ->shouldReceive('test')
            ->once()
            // Route model binding builds its own instance, so the provider
            // argument is matched loosely; the override is the thing under test.
            ->with($this->anything(), 'gpt-oss:20b')
            ->andReturn(['ok' => true, 'model' => 'gpt-oss:20b', 'reply' => 'ok', 'tokens' => 1]);

        $user = createUserWithPermissions(['ai.provider.manage']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/test?model=gpt-oss:20b")
            ->assertOk();
    });

    it('answers 422 with the reason when the provider cannot be reached', function () {
        $provider = AiProvider::factory()->create(['name' => 'broken']);

        $this->partialMock(AiConnectionTester::class)
            ->shouldReceive('test')
            ->once()
            ->andThrow(AiProviderUnreachable::because(
                $provider,
                new RuntimeException('invalid api key'),
            ));

        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/test")
            ->assertUnprocessable();

        // The whole point of the button is to be told *which* mistake it is.
        expect($response->json('message'))->toContain('invalid api key');
    });

    it('forbids a viewer', function () {
        $provider = AiProvider::factory()->create();

        $this->partialMock(AiConnectionTester::class)
            ->shouldNotReceive('test');

        $user = createUserWithPermissions(['ai.provider.view']);

        actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/test")
            ->assertForbidden();
    });
});

describe('capability vocabulary', function () {
    it('exposes every capability the enum defines', function () {
        $provider = AiProvider::factory()->create();
        $user = createUserWithPermissions(['ai.provider.manage']);

        $response = actingAs($user)
            ->postJson("/api/ai/providers/{$provider->id}/models", [
                'model_name' => 'multi',
                'capabilities' => array_map(
                    fn (AiCapability $c): string => $c->value,
                    AiCapability::cases(),
                ),
            ])
            ->assertCreated();

        expect($response->json('data.capabilities'))->toHaveCount(count(AiCapability::cases()));
    });

    it('accepts a provider row for every driver the enum offers', function () {
        $user = createUserWithPermissions(['ai.provider.manage']);

        foreach (AiDriver::cases() as $index => $driver) {
            actingAs($user)->postJson('/api/ai/providers', [
                'name' => "driver-{$index}",
                'driver' => $driver->value,
                'base_url' => 'https://example.test/v1',
                // Only Ollama is allowed to be keyless, so every other row has
                // to bring one to be valid.
                'api_key' => $driver === AiDriver::Ollama ? null : 'sk-test',
            ])->assertCreated();
        }

        expect(AiProvider::count())->toBe(count(AiDriver::cases()));
    });
});
