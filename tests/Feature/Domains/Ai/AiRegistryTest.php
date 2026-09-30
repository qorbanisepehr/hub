<?php

use App\Domains\Ai\Enums\AiCapability;
use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Models\AiAgent;
use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;
use Illuminate\Database\QueryException;

describe('the AI permission group', function () {
    it('registers the R4 view/manage split', function () {
        $group = config('permissions.groups.ai');

        expect($group['permissions'])->toHaveKeys(['ai.provider.view', 'ai.provider.manage']);
    });

    it('does not leak a separate key-editing permission — keys are manage-only', function () {
        $permissions = array_keys(config('permissions.groups.ai.permissions'));

        expect($permissions)->toBe(['ai.provider.view', 'ai.provider.manage']);
    });
});

describe('AiProvider', function () {
    it('casts the driver to the enum and the flags to booleans', function () {
        $provider = AiProvider::factory()->driver(AiDriver::Ollama)->default()->create();

        expect($provider->driver)->toBe(AiDriver::Ollama)
            ->and($provider->is_default)->toBeTrue()
            ->and($provider->is_active)->toBeTrue();
    });

    it('keeps the api key unreadable in the serialized model', function () {
        $provider = AiProvider::factory()->withApiKey('sk-secret-value')->create();

        expect($provider->toArray())->not->toHaveKey('api_key')
            ->and($provider->hasApiKey())->toBeTrue();
    });

    it('does not store the api key in the clear', function () {
        AiProvider::factory()->withApiKey('sk-secret-value')->create();

        $stored = DB::table('ai_providers')->value('api_key');

        expect($stored)->toBeString()->not->toContain('sk-secret-value');
    });

    it('round-trips the api key through the cast', function () {
        $provider = AiProvider::factory()->withApiKey('sk-secret-value')->create();

        expect($provider->fresh()->api_key)->toBe('sk-secret-value');
    });

    it('reports a missing credential without inventing one', function () {
        expect(AiProvider::factory()->create()->hasApiKey())->toBeFalse();
    });

    it('promotes a second default by demoting the first', function () {
        $first = AiProvider::factory()->default()->create();
        $second = AiProvider::factory()->default()->create();

        expect($first->fresh()->is_default)->toBeFalse()
            ->and($second->fresh()->is_default)->toBeTrue()
            ->and(AiProvider::query()->where('is_default', true)->count())->toBe(1);
    });

    it('demotes the current default when an existing provider is re-promoted', function () {
        $first = AiProvider::factory()->default()->create();
        AiProvider::factory()->default()->create();

        $first->fresh()->update(['is_default' => true]);

        expect($first->fresh()->is_default)->toBeTrue()
            ->and(AiProvider::query()->where('is_default', true)->count())->toBe(1);
    });

    it('allows any number of non-default providers', function () {
        AiProvider::factory()->count(3)->create();

        expect(AiProvider::query()->where('is_default', true)->count())->toBe(0);
    });

    it('leaves other defaults alone when saving a non-default provider', function () {
        $default = AiProvider::factory()->default()->create();

        AiProvider::factory()->create();

        expect($default->fresh()->is_default)->toBeTrue();
    });

    it('rejects a duplicate name', function () {
        AiProvider::factory()->create(['name' => 'gateway']);

        AiProvider::factory()->create(['name' => 'gateway']);
    })->throws(QueryException::class);

    it('reads and writes the headers map', function () {
        $provider = AiProvider::factory()->create(['headers' => ['X-Team' => 'ops']]);

        expect($provider->fresh()->headers)->toBe(['X-Team' => 'ops']);
    });
});

describe('AiModel', function () {
    it('casts capabilities to the enum list', function () {
        $model = AiModel::factory()
            ->capabilities([AiCapability::Text, AiCapability::Embeddings])
            ->create();

        expect($model->fresh()->capabilities)->toEqual([AiCapability::Text, AiCapability::Embeddings]);
    });

    it('drops an unknown capability instead of failing the read', function () {
        $id = AiModel::factory()->create()->id;
        DB::table('ai_models')->where('id', $id)->update(['capabilities' => '["text","teext"]']);

        expect(AiModel::find($id)->capabilities)->toEqual([AiCapability::Text]);
    });

    it('refuses to store a capability that is not in the enum', function () {
        AiModel::factory()->create(['capabilities' => ['teext']]);
    })->throws(InvalidArgumentException::class);

    it('de-duplicates capabilities and keeps an empty list as an empty array', function () {
        $model = AiModel::factory()->capabilities([AiCapability::Text, AiCapability::Text])->create();

        expect($model->fresh()->capabilities)->toEqual([AiCapability::Text]);

        $empty = AiModel::factory()->capabilities([])->create();

        expect($empty->fresh()->capabilities)->toEqual([]);
    });

    it('falls back to the provider model name when no label is set', function () {
        $model = AiModel::factory()->create(['model_name' => 'gpt-4o-mini']);

        expect($model->displayLabel())->toBe('gpt-4o-mini')
            ->and($model->forceFill(['label' => 'Fast'])->displayLabel())->toBe('Fast');
    });

    it('rejects the same model name twice on one provider but allows it across providers', function () {
        $provider = AiProvider::factory()->create();
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'gpt-4o-mini']);

        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'gpt-4o-mini']);
    })->throws(QueryException::class);

    it('allows two providers to offer the same model name', function () {
        AiModel::factory()->create(['model_name' => 'gpt-4o-mini']);
        AiModel::factory()->create(['model_name' => 'gpt-4o-mini']);

        expect(AiModel::query()->where('model_name', 'gpt-4o-mini')->count())->toBe(2);
    });

    it('deletes its models with the provider', function () {
        $provider = AiProvider::factory()->create();
        AiModel::factory()->create(['provider_id' => $provider->id]);

        $provider->delete();

        expect(AiModel::query()->count())->toBe(0);
    });
});

describe('AiAgent', function () {
    it('is unbound until it names a provider', function () {
        expect(AiAgent::factory()->create()->isBound())->toBeFalse();

        $bound = AiAgent::factory()->create(['provider_id' => AiProvider::factory()->create()->id]);

        expect($bound->isBound())->toBeTrue();
    });

    it('resolves its provider and model bindings', function () {
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $agent = AiAgent::factory()->create(['provider_id' => $provider->id, 'model_id' => $model->id]);

        expect($agent->provider->is($provider))->toBeTrue()
            ->and($agent->model->is($model))->toBeTrue();
    });

    it('unbinds rather than deletes when its provider is deleted', function () {
        $provider = AiProvider::factory()->create();
        $agent = AiAgent::factory()->create(['provider_id' => $provider->id]);

        $provider->delete();

        expect($agent->fresh()->provider_id)->toBeNull()
            ->and($agent->fresh()->isBound())->toBeFalse();
    });

    it('rejects a duplicate agent key', function () {
        AiAgent::factory()->create(['key' => 'board-meeting.summary']);

        AiAgent::factory()->create(['key' => 'board-meeting.summary']);
    })->throws(QueryException::class);

    it('reads and writes the settings map', function () {
        $agent = AiAgent::factory()->create(['settings' => ['temperature' => 0.2]]);

        expect($agent->fresh()->settings)->toBe(['temperature' => 0.2]);
    });
});
