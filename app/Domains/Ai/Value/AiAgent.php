<?php

namespace App\Domains\Ai\Value;

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Exceptions\InvalidSetting;
use Laravel\Ai\Contracts\Agent;

/**
 * The registry row that binds a domain's agent to a provider and a model
 * (D28), plus the label and per-run settings that belong to the agent itself.
 *
 * Both binding halves are optional and default on their own, in the order the
 * resolver already used: an agent that names no provider inherits the default
 * one, an agent that names no model inherits its provider's default. That is
 * what makes a partially configured agent a normal state rather than a broken
 * one — leaving the model blank is how you say "whatever this provider prefers".
 *
 * The two are stored as names rather than row ids, and the difference is not
 * cosmetic: with ids, "provider 1 and model 3" can name a model that belongs to
 * a different provider, so the pair has to be checked on every write and a
 * direct write or a factory can still produce it. With names, the resolver
 * settles the provider first and reads the model *within* it, so the pair
 * cannot disagree about which provider serves the model. The write path still
 * checks it — see the AI registry service — because a binding that names a
 * model its provider does not serve should be refused where it is written
 * rather than quietly ignored later.
 *
 * `agent_class` is a plain string, not a `#[Model]` attribute on the agent
 * class: the SDK's model selection would be a compile-time constant, while the
 * binding here is data an administrator can change per environment.
 */
final class AiAgent implements Setting
{
    public const GROUP = 'ai';

    public static function group(): string
    {
        return self::GROUP;
    }

    public static function handles(string $key): bool
    {
        return AiRegistryKey::agentKey($key) !== null;
    }

    public static function keyPrefix(): ?string
    {
        return AiRegistryKey::AGENTS;
    }

    public function key(): string
    {
        return AiRegistryKey::AGENTS.$this->key;
    }

    public function agentKey(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return class-string<Agent>
     */
    public function agentClass(): string
    {
        return $this->agentClass;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    /**
     * Per-run knobs the agent keeps as its own business (§۶.4) — `timeout`
     * today.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings;
    }

    /** Whether this agent names its own binding rather than inheriting the default. */
    public function isBound(): bool
    {
        return $this->provider !== null || $this->model !== null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $agentClass,
        private readonly ?string $provider = null,
        private readonly ?string $model = null,
        private readonly array $settings = [],
    ) {
        AiRegistryKey::assertLegalSegment($key, 'agent key');
    }

    /**
     * @return array<string, mixed>
     */
    public function toValue(): array
    {
        return [
            'label' => $this->label,
            'agent_class' => $this->agentClass,
            'provider' => $this->provider,
            'model' => $this->model,
            'settings' => $this->settings,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function toSecrets(): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, string>|null  $secrets
     */
    public static function fromStored(string $key, array $value, ?array $secrets): static
    {
        $agentKey = AiRegistryKey::agentKey($key);

        if ($agentKey === null) {
            throw InvalidSetting::unknownKey(self::class, $key);
        }

        return new self(
            key: $agentKey,
            label: self::parseText($key, 'label', $value['label'] ?? null),
            agentClass: self::parseText($key, 'agent_class', $value['agent_class'] ?? null),
            provider: self::parseProvider($key, $value['provider'] ?? null),
            model: self::parseModel($key, $value['model'] ?? null),
            settings: self::parseSettings($key, $value['settings'] ?? []),
        );
    }

    public function withLabel(string $label): self
    {
        return $this->with(['label' => $label]);
    }

    /**
     * Point the agent at a provider and a model, each optional so each can be
     * left to its own default.
     */
    public function boundTo(?string $provider, ?string $model): self
    {
        return $this->with(['provider' => $provider, 'model' => $model]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function withSettings(array $settings): self
    {
        return $this->with(['settings' => $settings]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function with(array $overrides): self
    {
        $fields = [
            'key' => $this->key,
            'label' => $this->label,
            'agentClass' => $this->agentClass,
            'provider' => $this->provider,
            'model' => $this->model,
            'settings' => $this->settings,
        ];

        return new self(...($overrides + $fields));
    }

    private static function parseText(string $key, string $field, mixed $given): string
    {
        if (! is_string($given) || $given === '') {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                sprintf('%s must be a non-empty string, %s given.', $field, get_debug_type($given)),
            );
        }

        return $given;
    }

    /**
     * Each half of the binding is checked against the pattern for what it names,
     * because they are not the same kind of thing: a provider name is an
     * identifier this hub invents, while a model name is whatever the provider
     * publishes and routinely carries a colon.
     */
    private static function parseProvider(string $key, mixed $given): ?string
    {
        $provider = self::nullable($key, 'provider', $given);

        if ($provider !== null && ! AiRegistryKey::isLegalSegment($provider)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                sprintf('provider must be a provider name, [%s] given.', $provider),
            );
        }

        return $provider;
    }

    private static function parseModel(string $key, mixed $given): ?string
    {
        $model = self::nullable($key, 'model', $given);

        if ($model !== null && ! AiRegistryKey::isLegalModelName($model)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                sprintf('model must be a model name, [%s] given.', $model),
            );
        }

        return $model;
    }

    private static function nullable(string $key, string $field, mixed $given): ?string
    {
        if ($given === null || $given === '') {
            return null;
        }

        if (! is_string($given)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                sprintf('%s must be a name or absent, %s given.', $field, get_debug_type($given)),
            );
        }

        return $given;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseSettings(string $key, mixed $given): array
    {
        if (! is_array($given)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'settings must be an object, '.get_debug_type($given).' given.',
            );
        }

        return $given;
    }
}
