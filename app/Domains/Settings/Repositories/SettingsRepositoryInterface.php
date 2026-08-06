<?php

namespace App\Domains\Settings\Repositories;

interface SettingsRepositoryInterface
{
    /**
     * Get the raw settings stored under the given key.
     *
     * @return array<string, mixed>
     */
    public function get(string $key): array;

    /**
     * Persist the given settings under the given key, replacing any previous value.
     *
     * @param  array<string, mixed>  $value
     */
    public function set(string $key, array $value): void;

    /**
     * Unix timestamp of the last write for the given key, or 0 when never written.
     */
    public function lastModified(string $key): int;

    /**
     * Drop any in-memory cache so the next read hits the source of truth.
     */
    public function flush(): void;
}
