<?php

namespace App\Domains\Ai\Value;

use App\Contracts\Settings\Setting;
use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Services\AiProviderResolver;
use App\Domains\Settings\Exceptions\InvalidSetting;
use InvalidArgumentException;

/**
 * One bring-your-own-key credential and everything reachable through it: the
 * driver it speaks, where it lives, what it authenticates with, and the models
 * it serves. All of it is one aggregate, so it is one row.
 *
 * The provider's name is its key and nothing else. There is no `name` column
 * beside it, because a column would need a unique index to stop two rows
 * claiming the same name and the index would need the column to do it — two
 * artifacts to keep in step, with the second one only there to disagree. As
 * the key it is enforced by the primary key itself, which is the only kind of
 * uniqueness that cannot be forgotten.
 *
 * `default_model` is a named pointer rather than "whichever model comes first",
 * and that is not a stylistic choice: `models` is a jsonb object, and jsonb
 * normalizes object keys rather than preserving the order they were written
 * in, so a positional answer would silently change the moment the setting was
 * written by a different version or reordered by an UPDATE. Naming the default
 * is the only way the answer survives the round trip.
 *
 * The credential is the private half and is the one thing that must never be
 * in `toValue()`. Splitting it into its own encrypted column is what makes that
 * a structural impossibility rather than a rule: the model's `$hidden` keeps it
 * out of every serialization, and there is no key in the public half that
 * could hold it by accident.
 */
final class AiProvider implements Setting
{
    public const GROUP = 'ai';

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, AiModel>  $models
     */
    private function __construct(
        private readonly string $name,
        private readonly AiDriver $driver,
        private readonly ?string $baseUrl,
        private readonly array $headers,
        private readonly ?string $apiKey,
        private readonly bool $isActive,
        private readonly array $models,
        private readonly ?string $defaultModel,
    ) {}

    public static function group(): string
    {
        return self::GROUP;
    }

    public static function handles(string $key): bool
    {
        return AiRegistryKey::providerName($key) !== null;
    }

    public static function keyPrefix(): ?string
    {
        return AiRegistryKey::PROVIDERS;
    }

    public function key(): string
    {
        return AiRegistryKey::PROVIDERS.$this->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function driver(): AiDriver
    {
        return $this->driver;
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * The credential itself.
     *
     * Read in exactly one place — {@see AiProviderResolver::configFor()} —
     * which is what keeps §۶.5's single decryption site a single site. The
     * setting cannot keep the secret away from the resolver, because the
     * resolver is what turns it into runtime config; what it does keep is the
     * secret away from every serialization, which is the half that reaches the
     * outside world.
     */
    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    /** Whether a credential is stored. Never reveals it. */
    public function hasCredential(): bool
    {
        return filled($this->apiKey);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return array<string, AiModel>
     */
    public function models(): array
    {
        return $this->models;
    }

    /**
     * The model this provider falls back to, or null when it names none.
     *
     * This is the stored pointer, not the answer to "what will you run". The two
     * differ: a pointer is allowed to name a model that has since been
     * deactivated, because deactivating a model is an ordinary thing for an
     * administrator to do and should not require editing the default first.
     * Resolution therefore treats an inactive target as no pointer at all, and
     * answering here would be answering a question nobody asked.
     */
    public function defaultModel(): ?string
    {
        return $this->defaultModel;
    }

    /**
     * The model resolution should actually use, or null when it has none.
     *
     * Falls back to the alphabetically first active model so the answer is
     * stable: jsonb reorders object keys, so "the first one" is not a thing that
     * survives a round trip, but a sorted name is.
     */
    public function preferredModel(): ?string
    {
        $default = $this->defaultModel;

        if ($default !== null && ($this->models[$default]->isActive ?? false)) {
            return $default;
        }

        $active = array_keys(array_filter(
            $this->models,
            static fn (AiModel $model): bool => $model->isActive,
        ));

        sort($active);

        return $active[0] ?? null;
    }

    public function model(string $name): ?AiModel
    {
        return $this->models[$name] ?? null;
    }

    public function hasModel(string $name): bool
    {
        return isset($this->models[$name]);
    }

    /**
     * Whether this provider can actually be asked to run that model.
     *
     * Separate from {@see hasModel()} because "the registry mentions it" and
     * "we will use it" are different questions. An administrator who switches a
     * model off means it, so every answer that could actually send a request
     * has to ask this one instead — a stale name must not be able to reach a
     * gateway that was deliberately taken out of service.
     */
    public function hasActiveModel(string $name): bool
    {
        return $this->models[$name]->isActive ?? false;
    }

    /**
     * The names of this provider's usable models.
     *
     * The order is whatever jsonb hands back, and it is not the order they were
     * written in: an object column normalizes its keys, so writing
     * `zeta:latest` then `alpha:1b` reads back as `alpha:1b, zeta:latest`. That
     * is why {@see defaultModel()} exists rather than a "first model" rule —
     * nothing here may depend on this order being stable.
     *
     * @return list<string>
     */
    public function activeModelNames(): array
    {
        return array_values(array_keys(array_filter(
            $this->models,
            static fn (AiModel $model): bool => $model->isActive,
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function toValue(): array
    {
        return [
            'driver' => $this->driver->value,
            'base_url' => $this->baseUrl,
            'headers' => $this->headers,
            'is_active' => $this->isActive,
            'default_model' => $this->defaultModel,
            'models' => array_map(
                static fn (AiModel $model): array => $model->toArray(),
                $this->models,
            ),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function toSecrets(): ?array
    {
        return $this->apiKey === null ? null : ['api_key' => $this->apiKey];
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, string>|null  $secrets
     */
    public static function fromStored(string $key, array $value, ?array $secrets): static
    {
        $name = AiRegistryKey::providerName($key);

        if ($name === null) {
            throw InvalidSetting::unknownKey(self::class, $key);
        }

        $models = self::parseModels($name, $value['models'] ?? null);
        $defaultModel = $value['default_model'] ?? null;

        if ($defaultModel !== null && (! is_string($defaultModel) || ! isset($models[$defaultModel]))) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'default_model must name one of the models it lists, or be absent. Given: '
                    .(is_string($defaultModel) ? $defaultModel : get_debug_type($defaultModel)).'.',
            );
        }

        return new self(
            name: $name,
            driver: self::parseDriver($key, $value['driver'] ?? null),
            baseUrl: self::parseBaseUrl($key, $value['base_url'] ?? null),
            headers: self::parseHeaders($key, $value['headers'] ?? []),
            apiKey: self::parseApiKey($key, $secrets),
            isActive: self::parseFlag($key, 'is_active', $value['is_active'] ?? null),
            models: $models,
            defaultModel: $defaultModel,
        );
    }

    /**
     * A provider with no models yet, which is a normal state: the row exists so
     * the credential is stored, and the models are discovered or added later.
     */
    public static function make(string $name, AiDriver $driver): self
    {
        AiRegistryKey::assertLegalSegment($name, 'provider name');

        return new self(
            name: $name,
            driver: $driver,
            baseUrl: null,
            headers: [],
            apiKey: null,
            isActive: true,
            models: [],
            defaultModel: null,
        );
    }

    public function withBaseUrl(?string $baseUrl): self
    {
        return $this->with(['baseUrl' => $baseUrl]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        return $this->with(['headers' => $headers]);
    }

    /**
     * Set or clear the credential. Null clears it, so a provider can be made
     * credential-free in the same request that stores everything else.
     */
    public function withApiKey(?string $apiKey): self
    {
        return $this->with(['apiKey' => $apiKey]);
    }

    public function asActive(bool $isActive): self
    {
        return $this->with(['isActive' => $isActive]);
    }

    /**
     * Replace the provider's model list wholesale.
     *
     * Replacing rather than merging is what lets a model be deleted: a merge
     * would keep an entry the caller no longer sent, and the provider would go
     * on serving a model an administrator believes they removed. The default
     * has to be named from the new list, because it is validated against it.
     *
     * The default is a required argument rather than an optional one that
     * defaults to null. There is no "leave it alone" reading here — passing
     * null *clears* it — so a defaulted parameter would turn a caller who forgot
     * the question into a provider that quietly lost its default model.
     *
     * @param  array<string, AiModel>  $models
     */
    public function withModels(array $models, ?string $defaultModel): self
    {
        foreach (array_keys($models) as $name) {
            AiRegistryKey::assertLegalModelName((string) $name);
        }

        return $this->with(['models' => $models, 'defaultModel' => $defaultModel]);
    }

    /**
     * A copy with some fields replaced.
     *
     * Spreading into the named parameters is what lets a field be set back to
     * `null`. A `withX(?string $x)` that falls back with `?? $this->x` cannot
     * clear anything, which would quietly make `withApiKey(null)` — the way to
     * remove a credential — a no-op.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function with(array $overrides): self
    {
        $fields = [
            'name' => $this->name,
            'driver' => $this->driver,
            'baseUrl' => $this->baseUrl,
            'headers' => $this->headers,
            'apiKey' => $this->apiKey,
            'isActive' => $this->isActive,
            'models' => $this->models,
            'defaultModel' => $this->defaultModel,
        ];

        // Spread rather than pass each field: spreading a map of named
        // arguments is what lets a field be set back to null.
        return new self(...($overrides + $fields));
    }

    private static function parseDriver(string $key, mixed $given): AiDriver
    {
        if (! is_string($given)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'driver must be a string, '.get_debug_type($given).' given.',
            );
        }

        return AiDriver::tryFrom($given) ?? throw InvalidSetting::malformed(
            self::class,
            $key,
            sprintf('[%s] is not a driver. Known: %s.', $given, implode(', ', array_column(AiDriver::cases(), 'value'))),
        );
    }

    private static function parseBaseUrl(string $key, mixed $given): ?string
    {
        if ($given === null || $given === '') {
            return null;
        }

        if (! is_string($given)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'base_url must be a string or absent, '.get_debug_type($given).' given.',
            );
        }

        return $given;
    }

    /**
     * @return array<string, string>
     */
    private static function parseHeaders(string $key, mixed $given): array
    {
        if (! is_array($given)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'headers must be an object, '.get_debug_type($given).' given.',
            );
        }

        $headers = [];

        foreach ($given as $header => $value) {
            if (! is_string($value)) {
                throw InvalidSetting::malformed(
                    self::class,
                    $key,
                    sprintf('the header [%s] must be a string, %s given.', $header, get_debug_type($value)),
                );
            }

            $headers[(string) $header] = $value;
        }

        return $headers;
    }

    /**
     * @param  array<string, string>|null  $secrets
     */
    private static function parseApiKey(string $key, ?array $secrets): ?string
    {
        $given = $secrets['api_key'] ?? null;

        if ($given === null) {
            return null;
        }

        if (! is_string($given)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'the stored credential must be a string, '.get_debug_type($given).' given.',
            );
        }

        return $given;
    }

    private static function parseFlag(string $key, string $field, mixed $given): bool
    {
        if (! is_bool($given)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                sprintf('%s must be a boolean, %s given.', $field, get_debug_type($given)),
            );
        }

        return $given;
    }

    /**
     * @return array<string, AiModel>
     */
    private static function parseModels(string $provider, mixed $given): array
    {
        if (! is_array($given)) {
            throw InvalidSetting::malformed(
                self::class,
                AiRegistryKey::PROVIDERS.$provider,
                'models must be an object keyed by model name, '.get_debug_type($given).' given.',
            );
        }

        $models = [];

        foreach ($given as $name => $model) {
            if (! is_array($model)) {
                throw InvalidSetting::malformed(
                    self::class,
                    AiRegistryKey::PROVIDERS.$provider,
                    sprintf('the entry for model [%s] must be an object, %s given.', $name, get_debug_type($model)),
                );
            }

            if (! AiRegistryKey::isLegalModelName((string) $name)) {
                throw InvalidSetting::malformed(
                    self::class,
                    AiRegistryKey::PROVIDERS.$provider,
                    sprintf('[%s] is not a usable model name.', $name),
                );
            }

            try {
                $models[(string) $name] = AiModel::fromStored((string) $name, $model);
            } catch (InvalidArgumentException $e) {
                throw InvalidSetting::malformed(self::class, AiRegistryKey::PROVIDERS.$provider, $e->getMessage());
            }
        }

        return $models;
    }
}
