<?php

namespace App\Domains\Settings\Repositories;

class FileSettingsRepository implements SettingsRepositoryInterface
{
    /**
     * In-memory cache for a single request.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $path = $this->path($key);

        if (! is_file($path)) {
            return $this->cache[$key] = [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->cache[$key] = [];
        }

        return $this->cache[$key] = is_array($decoded) ? $decoded : [];
    }

    public function set(string $key, array $value): void
    {
        $path = $this->path($key);

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        $tmpPath = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        file_put_contents(
            $tmpPath,
            json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        @rename($tmpPath, $path);

        $this->cache[$key] = $value;
    }

    public function lastModified(string $key): int
    {
        $path = $this->path($key);

        return is_file($path) ? (int) filemtime($path) : 0;
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    private function path(string $key): string
    {
        $directory = rtrim((string) config('settings.storage_path'), DIRECTORY_SEPARATOR);

        return $directory.DIRECTORY_SEPARATOR.$key.'.json';
    }
}
