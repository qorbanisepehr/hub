<?php

namespace App\Domains\Ai\Value;

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Exceptions\InvalidSetting;

/**
 * Which provider is the default — the one an agent runs against when it names
 * none.
 *
 * This is a row of its own rather than an `is_default` flag on every provider,
 * and the difference is a whole class of bug. The flag has to be made unique:
 * promoting one provider means updating every other one to clear it, so the
 * answer depends on a second write having happened, and a request in between —
 * or a write that fails — leaves two rows claiming to be the default, or none.
 * Here there is at most one row and it names one provider, so there is nothing
 * to keep consistent.
 *
 * Having no row is a supported state (D24: nothing configured breaks nothing),
 * which is why the absence of this setting is meaningful rather than an error.
 */
final class AiDefaultProvider implements Setting
{
    public const GROUP = 'ai';

    public const KEY = 'default_provider';

    private function __construct(private readonly string $provider) {}

    public static function group(): string
    {
        return self::GROUP;
    }

    public static function handles(string $key): bool
    {
        return $key === self::KEY;
    }

    public static function keyPrefix(): ?string
    {
        return null;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public static function make(string $provider): self
    {
        AiRegistryKey::assertLegalSegment($provider, 'provider name');

        return new self($provider);
    }

    /**
     * @return array<string, mixed>
     */
    public function toValue(): array
    {
        return ['provider' => $this->provider];
    }

    /**
     * @return array<string, string>|null
     */
    public function toSecrets(): ?array
    {
        return null;
    }

    /**
     * The stored name is checked for legality but not for existence: this
     * setting cannot see the provider table, and it should not. A pointer to a
     * provider that has since been deleted is exactly the stale-binding case
     * the resolver is built to survive by falling back, and refusing to read the
     * row would turn a recoverable degradation into a hard failure at boot.
     *
     * @param  array<string, mixed>  $value
     * @param  array<string, string>|null  $secrets
     */
    public static function fromStored(string $key, array $value, ?array $secrets): static
    {
        $provider = $value['provider'] ?? null;

        if (! is_string($provider) || ! AiRegistryKey::isLegalSegment($provider)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'provider must name a provider. Given: '
                    .(is_string($provider) ? $provider : get_debug_type($provider)).'.',
            );
        }

        return new self($provider);
    }
}
