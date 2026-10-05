<?php

use App\Contracts\Settings\Setting;
use App\Domains\Ai\Enums\AiCapability;
use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiDefaultProvider;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Ai\Value\AiRegistryKey;
use App\Domains\BoardMeeting\Agents\BoardMeetingSummaryAgent;
use App\Domains\Settings\Exceptions\InvalidSetting;
use App\Domains\Settings\Models\SystemSetting;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;

/**
 * A credential with a value nothing else in the tests uses, so a match on it is
 * unambiguous.
 */
const STORED_CREDENTIAL = 'sk-live-9f2a-do-not-leak';

function settingsStore(): SystemSettingsRepositoryInterface
{
    return app(SystemSettingsRepositoryInterface::class);
}

function aProvider(string $name = 'local'): AiProvider
{
    return AiProvider::make($name, AiDriver::Ollama)
        ->withApiKey(STORED_CREDENTIAL)
        ->withBaseUrl('http://localhost:11434')
        ->withHeaders(['X-Trace' => 'on'])
        ->withModels([
            'gemma3:latest' => new AiModel('gemma3:latest', 'Gemma 3', [AiCapability::Text], true),
            'old:1b' => new AiModel('old:1b', null, [], false),
        ], 'gemma3:latest');
}

/** The raw row, bypassing the model, so the stored column values are visible. */
function rawSetting(string $key, array $value): object
{
    DB::table('system_settings')->insert([
        'group' => 'ai',
        'key' => $key,
        'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('system_settings')->where('group', 'ai')->where('key', $key)->first();
}

describe('round trip', function () {
    it('reads back exactly what was written', function () {
        settingsStore()->put(aProvider());

        $read = settingsStore()->get(AiProvider::class, 'providers/local');

        expect($read)->toBeInstanceOf(AiProvider::class)
            ->and($read->name())->toBe('local')
            ->and($read->driver())->toBe(AiDriver::Ollama)
            ->and($read->baseUrl())->toBe('http://localhost:11434')
            ->and($read->headers())->toBe(['X-Trace' => 'on'])
            ->and($read->isActive())->toBeTrue()
            ->and($read->apiKey())->toBe(STORED_CREDENTIAL)
            ->and($read->defaultModel())->toBe('gemma3:latest')
            ->and($read->activeModelNames())->toBe(['gemma3:latest'])
            ->and($read->model('gemma3:latest')?->label)->toBe('Gemma 3')
            ->and($read->model('gemma3:latest')?->capabilities)->toBe([AiCapability::Text])
            ->and($read->model('old:1b')?->isActive)->toBeFalse();
    });

    it('returns null for a key nothing is stored under', function () {
        expect(settingsStore()->get(AiProvider::class, 'providers/absent'))->toBeNull()
            ->and(settingsStore()->exists(AiProvider::class, 'providers/absent'))->toBeFalse()
            ->and(settingsStore()->lastModified(AiProvider::class, 'providers/absent'))->toBe(0);
    });

    it('replaces a row instead of adding a second one for the same key', function () {
        settingsStore()->put(aProvider());
        settingsStore()->put(aProvider()->withBaseUrl('http://ollama.internal:11434'));

        expect(DB::table('system_settings')->count())->toBe(1)
            ->and(settingsStore()->get(AiProvider::class, 'providers/local')?->baseUrl())
            ->toBe('http://ollama.internal:11434');
    });

    it('lists every setting of a kind, ordered by key', function () {
        settingsStore()->put(aProvider('zed'));
        settingsStore()->put(aProvider('alpha'));

        $names = array_map(
            fn (AiProvider $provider): string => $provider->name(),
            settingsStore()->all(AiProvider::class),
        );

        expect($names)->toBe(['alpha', 'zed']);
    });

    it('keeps other kinds in the same group out of a listing', function () {
        settingsStore()->put(aProvider());
        settingsStore()->put(AiDefaultProvider::make('local'));

        expect(settingsStore()->all(AiProvider::class))->toHaveCount(1)
            ->and(settingsStore()->all(AiDefaultProvider::class))->toHaveCount(1);
    });

    it('reports when a setting was last written and forgets it on request', function () {
        settingsStore()->put(aProvider());

        expect(settingsStore()->lastModified(AiProvider::class, 'providers/local'))
            ->toBe(now()->getTimestamp());

        settingsStore()->forget(AiProvider::class, 'providers/local');

        expect(settingsStore()->exists(AiProvider::class, 'providers/local'))->toBeFalse()
            ->and(DB::table('system_settings')->count())->toBe(0);
    });

    it('forgets a key that is not there without complaining', function () {
        settingsStore()->forget(AiProvider::class, 'providers/never-stored');

        expect(settingsStore()->exists(AiProvider::class, 'providers/never-stored'))->toBeFalse();
    });
});

describe('the credential', function () {
    /**
     * Three separate places a secret can end up, and each one is checked by
     * content rather than by key name. Asking "is there an `api_key` key?" would
     * pass while the plaintext sat under some other name, which is precisely how
     * it reached a response body earlier today.
     */
    it('never appears in plaintext in the column, the attributes, or the array', function () {
        settingsStore()->put(aProvider());

        $raw = DB::table('system_settings')->where('group', 'ai')->where('key', 'providers/local')->first();
        $model = SystemSetting::query()->where('group', 'ai')->where('key', 'providers/local')->first();

        expect(json_encode($raw, JSON_UNESCAPED_SLASHES))->not->toContain(STORED_CREDENTIAL)
            ->and(json_encode($model->getAttributes()))->not->toContain(STORED_CREDENTIAL)
            ->and(json_encode($model->toArray()))->not->toContain(STORED_CREDENTIAL);

        // The three assertions above are not vacuous: it is really stored, and
        // really retrievable by the one reader allowed to ask for it.
        expect($model->secrets)->toBe(['api_key' => STORED_CREDENTIAL])
            ->and($model->hasSecrets())->toBeTrue()
            ->and(settingsStore()->get(AiProvider::class, 'providers/local')?->hasCredential())->toBeTrue();
    });

    it('is absent from a provider that was never given one', function () {
        settingsStore()->put(AiProvider::make('local', AiDriver::Ollama));

        $raw = DB::table('system_settings')->where('group', 'ai')->where('key', 'providers/local')->first();

        expect($raw->secrets)->toBeNull()
            ->and(settingsStore()->get(AiProvider::class, 'providers/local')?->hasCredential())->toBeFalse();
    });

    it('can be removed by writing null over it', function () {
        settingsStore()->put(aProvider());

        settingsStore()->put(
            settingsStore()->get(AiProvider::class, 'providers/local')->withApiKey(null),
        );

        $raw = DB::table('system_settings')->where('group', 'ai')->where('key', 'providers/local')->first();

        expect($raw->secrets)->toBeNull()
            ->and(settingsStore()->get(AiProvider::class, 'providers/local')?->apiKey())->toBeNull();
    });
});

describe('keys the store does not own', function () {
    it('refuses a provider key with a typo in its prefix', function () {
        expect(AiProvider::handles('provder/local'))->toBeFalse()
            ->and(AiProvider::handles('providers/local'))->toBeTrue();

        expect(fn () => settingsStore()->get(AiProvider::class, 'provder/local'))
            ->toThrow(InvalidSetting::class);
    });

    it('refuses a provider key whose name could not survive a route or a config key', function () {
        foreach (['providers/local ollama', 'providers/a/b', 'providers/local:1', 'providers/', 'providers/-local'] as $key) {
            expect(AiProvider::handles($key))->toBeFalse();
        }

        expect(fn () => settingsStore()->put(AiProvider::make('local ollama', AiDriver::Ollama)))
            ->toThrow(InvalidArgumentException::class);
    });

    it('refuses a key that belongs to another kind of setting', function () {
        expect(AiProvider::handles('default_provider'))->toBeFalse()
            ->and(AiAgent::handles('providers/local'))->toBeFalse()
            ->and(AiDefaultProvider::handles('providers/local'))->toBeFalse();
    });

    it('refuses to store under a key the setting does not produce', function () {
        $setting = aProvider();

        // A setting whose own key its class says it does not own is refused on
        // write, so a mismatch cannot become a row nothing can read.
        $smuggled = new class($setting) implements Setting
        {
            public function __construct(private readonly AiProvider $inner) {}

            public static function group(): string
            {
                return AiProvider::group();
            }

            public static function handles(string $key): bool
            {
                return false;
            }

            public static function keyPrefix(): ?string
            {
                return AiProvider::keyPrefix();
            }

            public function key(): string
            {
                return 'providers/local';
            }

            public function toValue(): array
            {
                return $this->inner->toValue();
            }

            public function toSecrets(): ?array
            {
                return $this->inner->toSecrets();
            }

            public static function fromStored(string $key, array $value, ?array $secrets): static
            {
                return AiProvider::fromStored($key, $value, $secrets);
            }
        };

        expect(fn () => settingsStore()->put($smuggled))->toThrow(InvalidSetting::class);
    });
});

describe('settings that are not the shape they claim', function () {
    it('refuses a driver it does not know', function () {
        rawSetting('providers/local', [
            'driver' => 'teapot',
            'base_url' => null,
            'headers' => [],
            'is_active' => true,
            'default_model' => null,
            'models' => [],
        ]);

        expect(fn () => settingsStore()->get(AiProvider::class, 'providers/local'))
            ->toThrow(InvalidSetting::class);
    });

    it('refuses a provider with no driver at all rather than defaulting one', function () {
        rawSetting('providers/local', [
            'base_url' => null,
            'headers' => [],
            'is_active' => true,
            'models' => [],
        ]);

        expect(fn () => settingsStore()->get(AiProvider::class, 'providers/local'))
            ->toThrow(InvalidSetting::class);
    });

    it('refuses a default model that is not one of its models', function () {
        rawSetting('providers/local', [
            'driver' => 'ollama',
            'base_url' => null,
            'headers' => [],
            'is_active' => true,
            'default_model' => 'gpt-4o',
            'models' => [
                'gemma3:latest' => ['label' => null, 'capabilities' => [], 'is_active' => true],
            ],
        ]);

        expect(fn () => settingsStore()->get(AiProvider::class, 'providers/local'))
            ->toThrow(InvalidSetting::class);
    });

    it('refuses a model entry with an unknown capability', function () {
        rawSetting('providers/local', [
            'driver' => 'ollama',
            'base_url' => null,
            'headers' => [],
            'is_active' => true,
            'default_model' => null,
            'models' => [
                'gemma3:latest' => ['label' => null, 'capabilities' => ['teext'], 'is_active' => true],
            ],
        ]);

        expect(fn () => settingsStore()->get(AiProvider::class, 'providers/local'))
            ->toThrow(InvalidSetting::class);
    });

    it('refuses a header whose value is not a string', function () {
        rawSetting('providers/local', [
            'driver' => 'ollama',
            'base_url' => null,
            'headers' => ['X-Retry' => 3],
            'is_active' => true,
            'models' => [],
        ]);

        expect(fn () => settingsStore()->get(AiProvider::class, 'providers/local'))
            ->toThrow(InvalidSetting::class);
    });

    it('names the provider in the message, so a bad row can be found', function () {
        rawSetting('providers/local', ['driver' => 'teapot']);

        expect(fn () => settingsStore()->get(AiProvider::class, 'providers/local'))
            ->toThrow(InvalidSetting::class, 'providers/local');
    });

    it('refuses a default provider row that names nothing', function () {
        rawSetting('default_provider', ['provider' => null]);

        expect(fn () => settingsStore()->get(AiDefaultProvider::class, 'default_provider'))
            ->toThrow(InvalidSetting::class);
    });

    it('refuses an agent with no class to run', function () {
        rawSetting('agents/board-meeting.summary', ['label' => 'Summary']);

        expect(fn () => settingsStore()->get(AiAgent::class, 'agents/board-meeting.summary'))
            ->toThrow(InvalidSetting::class);
    });
});

describe('the other two settings', function () {
    it('round trips the default provider pointer', function () {
        settingsStore()->put(AiDefaultProvider::make('local'));

        expect(settingsStore()->get(AiDefaultProvider::class, 'default_provider')?->provider())
            ->toBe('local');
    });

    it('round trips an agent binding, keeping the two halves independently optional', function () {
        $agent = new AiAgent(
            key: 'board-meeting.summary',
            label: 'Moulid khalase',
            agentClass: BoardMeetingSummaryAgent::class,
            model: 'gemma3:latest',
            settings: ['timeout' => 180],
        );

        settingsStore()->put($agent);

        $read = settingsStore()->get(AiAgent::class, 'agents/board-meeting.summary');

        expect($read)->toBeInstanceOf(AiAgent::class)
            ->and($read->agentKey())->toBe('board-meeting.summary')
            ->and($read->label())->toBe('Moulid khalase')
            ->and($read->provider())->toBeNull()
            ->and($read->model())->toBe('gemma3:latest')
            ->and($read->settings())->toBe(['timeout' => 180])
            ->and($read->isBound())->toBeTrue()
            ->and($read->toSecrets())->toBeNull();
    });

    it('reports an unbound agent as unbound', function () {
        $agent = new AiAgent('cv.rewrite', 'Rewrite', Agent::class);

        expect($agent->isBound())->toBeFalse();
    });
});

describe('the key space', function () {
    it('reads a provider name back out of its key', function () {
        expect(AiRegistryKey::providerName('providers/local'))->toBe('local')
            ->and(AiRegistryKey::providerName('agents/local'))->toBeNull()
            ->and(AiRegistryKey::providerName('providers/local/extra'))->toBeNull();
    });

    it('reads an agent key back out of its key', function () {
        expect(AiRegistryKey::agentKey('agents/board-meeting.summary'))->toBe('board-meeting.summary')
            ->and(AiRegistryKey::agentKey('providers/local'))->toBeNull();
    });
});

describe('a page of settings', function () {
    it('returns only the rows of the kind asked for, ordered by key', function () {
        settingsStore()->put(aProvider('zed'));
        settingsStore()->put(aProvider('alpha'));
        settingsStore()->put(new AiAgent('cv.rewrite', 'Rewrite', Agent::class));
        settingsStore()->put(AiDefaultProvider::make('alpha'));

        $page = settingsStore()->page(
            setting: AiProvider::class,
            term: null,
            searchable: [],
            sort: 'key',
            order: 'asc',
            page: 1,
            perPage: 50,
        );

        // The other two kinds live under the same `ai` group, so a listing that
        // selected on the group alone would hand back an agent and a pointer
        // where the client expects providers.
        expect($page->getCollection()->map(fn (AiProvider $p): string => $p->name())->all())
            ->toBe(['alpha', 'zed'])
            ->and($page->total())->toBe(2);
    });

    it('paginates, and reports the counts the client renders', function () {
        foreach (['alpha', 'mike', 'zed'] as $name) {
            settingsStore()->put(aProvider($name));
        }

        $page = settingsStore()->page(
            setting: AiProvider::class,
            term: null,
            searchable: [],
            sort: 'key',
            order: 'asc',
            page: 2,
            perPage: 2,
        );

        expect($page->getCollection()->map(fn (AiProvider $p): string => $p->name())->all())
            ->toBe(['zed'])
            ->and($page->total())->toBe(3)
            ->and($page->lastPage())->toBe(2);
    });

    it('reaches a field that only exists inside the document', function () {
        settingsStore()->put(aProvider('local')->withBaseUrl('https://ollama.internal:11434'));

        $page = settingsStore()->page(
            setting: AiProvider::class,
            term: 'ollama.internal',
            searchable: ['base_url'],
            sort: 'key',
            order: 'asc',
            page: 1,
            perPage: 50,
        );

        expect($page->total())->toBe(1);
    });

    it('finds a field by a fragment that the stored text would hide', function () {
        settingsStore()->put(aProvider('local')->withBaseUrl('https://ollama.internal:11434'));
        settingsStore()->put(aProvider('other')->withBaseUrl('http://other.internal:11434'));

        // The regression this guards: json_encode writes `https:\/\/…`, so the
        // raw column on SQLite holds no `//` to match, and on Postgres the
        // `jsonb LIKE text` operator does not exist at all. Matching through
        // `->>` is the only spelling that finds the row on either database —
        // which is why this asserts the whole `://` rather than a word.
        $page = settingsStore()->page(
            setting: AiProvider::class,
            term: '//ollama',
            searchable: ['base_url'],
            sort: 'key',
            order: 'asc',
            page: 1,
            perPage: 50,
        );

        expect($page->getCollection()->map(fn (AiProvider $p): string => $p->name())->all())
            ->toBe(['local']);
    });

    it('treats the wildcards in a term as characters', function () {
        settingsStore()->put(aProvider('a_b'));
        settingsStore()->put(aProvider('axb'));

        $page = settingsStore()->page(
            setting: AiProvider::class,
            term: 'a_b',
            searchable: ['key'],
            sort: 'key',
            order: 'asc',
            page: 1,
            perPage: 50,
        );

        expect($page->getCollection()->map(fn (AiProvider $p): string => $p->name())->all())
            ->toBe(['a_b']);
    });

    it('orders by a document field, with the key settling ties', function () {
        settingsStore()->put(aProvider('second')->withApiKey(null));
        settingsStore()->put(aProvider('first')->withApiKey(null));
        settingsStore()->put(aProvider('third')->withApiKey('sk-something'));

        $page = settingsStore()->page(
            setting: AiProvider::class,
            term: null,
            searchable: [],
            sort: 'is_active',
            order: 'asc',
            page: 1,
            perPage: 50,
        );

        // Two providers are active and one is not. Without the key tiebreaker
        // the two actives could come back in either order on different
        // databases, and page 1 of 1 would still claim to be one page.
        expect($page->getCollection()->map(fn (AiProvider $p): string => $p->name())->all())
            ->toBe(['first', 'second', 'third']);
    });

    it('refuses a field name that is not a plain document field', function () {
        settingsStore()->put(aProvider());

        // The one place in the store where a string becomes SQL, because a
        // column cannot be bound. A name carrying SQL is refused rather than
        // escaped, since a document field here is snake_case or it is a bug.
        expect(fn () => settingsStore()->page(
            setting: AiProvider::class,
            term: null,
            searchable: [],
            sort: '(select 1) as x, key --',
            order: 'asc',
            page: 1,
            perPage: 50,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('still refuses a row whose key its own kind does not claim', function () {
        settingsStore()->put(aProvider('good'));

        rawSetting('providers/has a space', ['driver' => 'ollama']);

        $page = settingsStore()->page(
            setting: AiProvider::class,
            term: null,
            searchable: [],
            sort: 'key',
            order: 'asc',
            page: 1,
            perPage: 50,
        );

        // The prefix narrows the query; `handles()` decides what belongs. A
        // row that has the prefix and not the shape is corrupt data, and the
        // difference matters: a bad prefix is slow, a bad shape is wrong.
        expect($page->getCollection()->map(fn (AiProvider $p): string => $p->name())->all())
            ->toBe(['good']);
    });
});
