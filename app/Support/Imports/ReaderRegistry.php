<?php

namespace App\Support\Imports;

use App\Support\Imports\Contract\TabularReader;
use InvalidArgumentException;

/**
 * Format name → reader. Mirrors `WriterRegistry` on the export side:
 * adding a format means registering a reader (in the service provider),
 * never editing ImportService (OCP).
 */
final class ReaderRegistry
{
    /** @var array<string, TabularReader> */
    private array $readers = [];

    public function register(string $format, TabularReader $reader): void
    {
        $this->readers[$format] = $reader;
    }

    public function has(string $format): bool
    {
        return isset($this->readers[$format]);
    }

    public function get(string $format): TabularReader
    {
        return $this->readers[$format]
            ?? throw new InvalidArgumentException("Unsupported import format [{$format}].");
    }
}
