<?php

namespace App\Contracts\Settings;

use App\Domains\Settings\Exceptions\InvalidSetting;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;

/**
 * One addressable piece of system configuration, with a shape.
 *
 * This is the load-bearing half of the generic settings store. A table that
 * accepts any `(group, key)` and any `jsonb` would still hold an unbounded key
 * space with no defined meaning, and `(ai, provder:local)` would be as legal as
 * `(ai, providers/local)` — a typo that saves happily and is discovered at the
 * far end of a paid request. A setting class instead enumerates the keys it
 * owns and gives them a shape, so an unknown key or a malformed setting is
 * refused where it is written.
 *
 * The split between {@see toValue()} and {@see toSecrets()} is what makes a
 * credential a different kind of thing rather than one more entry in a bag.
 * The private half goes to its own column, encrypted, so it is excluded from
 * every serialization by the model's `$hidden` instead of by each caller's
 * care. No setting may put a secret in `toValue()`; there is nowhere for one
 * to hide, and anything that did would be readable in `getAttributes()`.
 *
 * @see SystemSettingsRepositoryInterface
 */
interface Setting
{
    /** The group every key of this kind is stored under. */
    public static function group(): string;

    /** Whether this kind of setting owns the given key. */
    public static function handles(string $key): bool;

    /**
     * The shared opening of every key this kind owns, or null when it owns the
     * whole group.
     *
     * A narrowing hint for SQL, never the authority. {@see handles()} decides
     * what belongs to this kind and is checked on every read regardless; the
     * prefix exists only so a listing can be restricted in the query instead of
     * by loading the group and filtering in PHP. It is allowed to be wrong in
     * the direction of being too broad, because a key with the right prefix but
     * a malformed tail is still caught by `handles()` — which is the difference
     * between a slow read and an incorrect one.
     *
     * Null rather than `''` for "the whole group", so an empty string cannot be
     * mistaken for a prefix that matches nothing.
     */
    public static function keyPrefix(): ?string;

    /** The key this setting is stored under, inside {@see group()}. */
    public function key(): string;

    /**
     * The public half, as stored.
     *
     * @return array<string, mixed>
     */
    public function toValue(): array;

    /**
     * The private half, as stored. Null when there is nothing private.
     *
     * @return array<string, string>|null
     */
    public function toSecrets(): ?array;

    /**
     * Rebuild a setting from its stored halves.
     *
     * This is the read path's only guard against a row that is structurally
     * wrong, so it throws rather than defaulting: a setting that quietly
     * invents a missing driver or an unparseable model list is a
     * configuration error, and failing here is what keeps it from surfacing
     * as a request against the wrong model.
     *
     * @param  array<string, mixed>  $value
     * @param  array<string, string>|null  $secrets
     *
     * @throws InvalidSetting
     */
    public static function fromStored(string $key, array $value, ?array $secrets): static;
}
