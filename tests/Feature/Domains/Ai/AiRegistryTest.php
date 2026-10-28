<?php

use App\Domains\Ai\Enums\AiCapability;
use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Services\AiProviderService;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiDefaultProvider;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * The invariants the registry used to get from three tables.
 *
 * Dropping the tables took the constraints with them, so what these tests
 * actually guard now is `AiProviderService` re-establishing each one. They
 * belong here rather than on the value objects because a value object cannot
 * enforce any of them: uniqueness and cross-row agreement are properties of the
 * store, not of a document.
 *
 * Document-level validity — the credential never being in plaintext, malformed
 * rows being refused, capability coercion — belongs to
 * `SystemSettingsStoreTest`, which tests it once at the layer that owns it.
 */
function registryService(): AiProviderService
{
    return app(AiProviderService::class);
}

function registryStore(): SystemSettingsRepositoryInterface
{
    return app(SystemSettingsRepositoryInterface::class);
}

/**
 * Run a closure expected to throw, and hand back the exception.
 *
 * The point of capturing it rather than only asserting the type is that these
 * refusals are field-keyed on purpose: a `message`-only 422 renders as a banner
 * and leaves the offending input looking fine, so which key it landed on is
 * part of the contract, not a detail.
 */
function captureValidationException(callable $callback): ValidationException
{
    try {
        $callback();
    } catch (ValidationException $e) {
        return $e;
    }

    throw new RuntimeException('expected a ValidationException, none was thrown');
}

function registerProvider(
    string $name = 'gateway',
    AiDriver $driver = AiDriver::Ollama,
    ?string $apiKey = null,
    bool $isActive = true,
): AiProvider {
    return registryService()->createProvider(
        AiProvider::make($name, $driver)->withApiKey($apiKey)->asActive($isActive),
    );
}

/**
 * Add a model to a provider, taking the provider *by name* and re-reading it.
 *
 * The re-read is not tidiness. `addModel()` is handed the document to write and
 * answers with the new one, so a caller holding the pre-write value would let
 * the next call write against a model list that is one model behind — and the
 * duplicate-name refusal, which is checked against the passed document, would
 * never fire. That is the aggregate rule the whole design turns on.
 *
 * @param  list<AiCapability>  $capabilities
 */
function registerModel(AiProvider|string $provider, string $name, ?string $label = null, array $capabilities = [], bool $isActive = true): AiModel
{
    $current = $provider instanceof AiProvider
        ? registryService()->provider($provider->name())
        : registryService()->provider($provider);

    $updated = registryService()->addModel($current, new AiModel($name, $label, $capabilities, $isActive));

    return $updated->model($name) ?? throw new RuntimeException("model [{$name}] was not stored");
}

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

describe('provider names', function () {
    it('reads a stored provider back by name', function () {
        registerProvider('lab');

        expect(registryService()->provider('lab')->name())->toBe('lab');
    });

    // Was a UNIQUE index on a `name` column beside the key. Now it is the key
    // itself, which is the only kind of uniqueness that cannot be forgotten —
    // but a duplicate still has to be refused rather than silently overwriting,
    // because `put()` on an existing key replaces it.
    it('refuses a duplicate name instead of overwriting the first', function () {
        registerProvider('gateway');

        captureValidationException(fn () => registerProvider('gateway'));

        expect(registryService()->providers())->toHaveCount(1);
    });

    it('reports the duplicate against the name field', function () {
        registerProvider('gateway');

        $e = captureValidationException(fn () => registerProvider('gateway'));

        expect($e->errors())->toHaveKey('name');
    });

    it('allows any number of distinct providers', function () {
        registerProvider('a');
        registerProvider('b');
        registerProvider('c');

        expect(registryService()->providers())->toHaveCount(3);
    });
});

describe('the default provider', function () {
    // Was a boolean column that promoting a second provider had to demote on,
    // so the answer depended on a second write having happened. It is one row
    // naming one provider now, so there is nothing to keep consistent.
    it('promotes a provider without touching the others', function () {
        $first = registerProvider('first');
        registerProvider('second');

        registryService()->makeDefault('second');

        expect(registryService()->defaultProviderName())->toBe('second')
            ->and(registryStore()->get(AiDefaultProvider::class, AiDefaultProvider::KEY)?->provider())->toBe('second')
            ->and(registryStore()->get(AiProvider::class, $first->key()))->not->toBeNull();
    });

    it('replaces an existing default rather than demoting a row', function () {
        registerProvider('first');
        registerProvider('second');

        registryService()->makeDefault('first');
        registryService()->makeDefault('second');

        expect(registryService()->defaultProviderName())->toBe('second')
            ->and(registryStore()->all(AiDefaultProvider::class))->toHaveCount(1);
    });

    it('has no default until one is promoted', function () {
        registerProvider('a');
        registerProvider('b');

        expect(registryService()->defaultProviderName())->toBeNull();
    });
});

describe('provider credentials', function () {
    it('keeps the credential out of the serialized document', function () {
        $provider = registerProvider('cloud', AiDriver::OpenAI, 'sk-secret-value');

        expect($provider->toValue())->not->toHaveKey('api_key')
            ->and($provider->hasCredential())->toBeTrue();
    });

    it('does not store the credential in the clear', function () {
        registerProvider('cloud', AiDriver::OpenAI, 'sk-secret-value');

        $stored = DB::table('system_settings')->value('value');

        expect($stored)->toBeString()->not->toContain('sk-secret-value');
    });

    it('reports a missing credential without inventing one', function () {
        expect(registerProvider('bare')->hasCredential())->toBeFalse();
    });

    it('clears a credential when the provider is rewritten without one', function () {
        $provider = registerProvider('cloud', AiDriver::OpenAI, 'sk-secret-value');

        registryService()->updateProvider($provider->withApiKey(null));

        expect(registryService()->provider('cloud')->hasCredential())->toBeFalse();
    });
});

describe('models inside a provider', function () {
    it('casts capabilities to the enum list', function () {
        $provider = registerProvider();

        $added = registerModel($provider, 'gpt-4o-mini', capabilities: [AiCapability::Text, AiCapability::Embeddings]);

        expect($added->capabilities)->toEqual([AiCapability::Text, AiCapability::Embeddings]);
    });

    it('de-duplicates capabilities on the way back out, not on the way in', function () {
        $provider = registerProvider();

        // The constructor stores what it is given; the read is where a document
        // written by a hand-edited row or an older version is cleaned up. A
        // duplicated capability is not an error to refuse, just noise to drop.
        $added = registerModel($provider, 'gpt-4o-mini', capabilities: [AiCapability::Text, AiCapability::Text]);

        expect($added->capabilities)->toEqual([AiCapability::Text, AiCapability::Text])
            ->and(registryService()->provider('gateway')->model('gpt-4o-mini')?->capabilities)->toEqual([AiCapability::Text]);

        $empty = registerModel($provider, 'other', capabilities: []);

        expect($empty->capabilities)->toBe([]);
    });

    it('falls back to the model name when no label is set', function () {
        $provider = registerProvider();

        expect(registerModel($provider, 'gpt-4o-mini')->displayLabel())->toBe('gpt-4o-mini')
            ->and(registerModel($provider, 'gpt-4o', 'GPT-4o')->displayLabel())->toBe('GPT-4o');
    });

    // Was UNIQUE (provider_id, model_name) plus a jsonb map key. The map key is
    // the constraint, and it has to be refused explicitly: a jsonb object keeps
    // the last of a duplicated key with no error at all.
    it('refuses the same model name twice on one provider', function () {
        $provider = registerProvider();
        registerModel($provider, 'gpt-4o-mini');

        $e = captureValidationException(fn () => registerModel($provider, 'gpt-4o-mini'));

        expect($e->errors())->toHaveKey('model_name');
    });

    it('allows two providers to offer the same model name', function () {
        registerModel(registerProvider('alpha'), 'gpt-4o-mini');
        registerModel(registerProvider('beta'), 'gpt-4o-mini');

        expect(registryService()->provider('alpha')->hasModel('gpt-4o-mini'))->toBeTrue()
            ->and(registryService()->provider('beta')->hasModel('gpt-4o-mini'))->toBeTrue();
    });

    it('removes a model from the provider that owned it', function () {
        $provider = registerProvider();
        registerModel($provider, 'gpt-4o-mini');
        registerModel($provider, 'gpt-4o');

        // Re-read, for the same reason as registerModel(): the delete is checked
        // against the document it is handed.
        registryService()->deleteModel(registryService()->provider('gateway'), 'gpt-4o-mini');

        expect(registryService()->provider('gateway')->hasModel('gpt-4o-mini'))->toBeFalse()
            ->and(registryService()->provider('gateway')->hasModel('gpt-4o'))->toBeTrue();
    });

    // A deleted model used to go with its provider through cascade, so the
    // registry could never serve a name whose row was gone. Now the model lives
    // in the provider's own document, so deleting the provider takes it with it
    // by construction rather than by a database rule.
    it('takes its models with it when the provider is deleted', function () {
        $provider = registerProvider();
        registerModel($provider, 'gpt-4o-mini');

        registryService()->deleteProvider('gateway');

        expect(registryService()->providers())->toBe([])
            ->and(registryStore()->get(AiProvider::class, $provider->key()))->toBeNull();
    });
});

describe('agent bindings', function () {
    it('is unbound until it names a provider', function () {
        expect(new AiAgent('board-meeting.summary', 'Label', EchoAgent::class)->isBound())->toBeFalse()
            ->and((new AiAgent('board-meeting.summary', 'Label', EchoAgent::class, 'gateway'))->isBound())->toBeTrue();
    });

    it('reads back the provider and model it names', function () {
        $provider = registerProvider();
        registerModel($provider, 'gpt-4o-mini');

        $bound = registryService()->bindAgent(
            new AiAgent('board-meeting.summary', 'Label', EchoAgent::class),
            'gateway',
            'gpt-4o-mini',
        );

        expect($bound->provider())->toBe('gateway')
            ->and($bound->model())->toBe('gpt-4o-mini')
            ->and(registryService()->agent('board-meeting.summary')->model())->toBe('gpt-4o-mini');
    });

    // Was nullOnDelete. The registry had to do it itself once the foreign keys
    // were gone, or a binding would keep naming something that no longer exists.
    it('unbinds rather than dangling when its provider is deleted', function () {
        $provider = registerProvider();
        registryService()->bindAgent(new AiAgent('board-meeting.summary', 'Label', EchoAgent::class), 'gateway', null);

        registryService()->deleteProvider('gateway');

        expect(registryService()->agent('board-meeting.summary')->provider())->toBeNull()
            ->and(registryService()->agent('board-meeting.summary')->isBound())->toBeFalse();
    });

    // The bug the three-table shape could not prevent: `provider_id` and
    // `model_id` were read independently, so a row could pair a provider with
    // another provider's model and fail at the far end of the gateway.
    it('refuses a model the named provider does not serve', function () {
        registerProvider('alpha');
        registerModel(registerProvider('beta'), 'gpt-4o-mini');

        $e = captureValidationException(
            fn () => registryService()->bindAgent(
                new AiAgent('board-meeting.summary', 'Label', EchoAgent::class),
                'alpha',
                'gpt-4o-mini',
            ),
        );

        expect($e->errors())->toHaveKey('model');
    });

    it('refuses a binding to a provider that does not exist', function () {
        $e = captureValidationException(
            fn () => registryService()->bindAgent(new AiAgent('board-meeting.summary', 'Label', EchoAgent::class), 'ghost', null),
        );

        expect($e->errors())->toHaveKey('provider');
    });

    it('lets each half of the binding be set on its own', function () {
        $provider = registerProvider();
        registerModel($provider, 'gpt-4o-mini');

        registryService()->bindAgent(new AiAgent('board-meeting.summary', 'Label', EchoAgent::class), 'gateway', 'gpt-4o-mini');
        $modelOnly = registryService()->bindAgent(
            registryService()->agent('board-meeting.summary'),
            null,
            'gpt-4o-mini',
        );

        expect($modelOnly->provider())->toBeNull()
            ->and($modelOnly->model())->toBe('gpt-4o-mini');
    });

    it('reads and writes the settings map', function () {
        $bound = registryService()->bindAgent(
            new AiAgent('board-meeting.summary', 'Label', EchoAgent::class, null, null, ['timeout' => 180]),
            null,
            null,
        );

        expect($bound->settings())->toBe(['timeout' => 180])
            ->and(registryService()->agent('board-meeting.summary')->settings())->toBe(['timeout' => 180]);
    });
});
