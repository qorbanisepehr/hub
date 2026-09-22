<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\TabularWriter;
use InvalidArgumentException;

/**
 * Format name → writer. Adding a format means registering a writer (in the
 * service provider), never editing ExportService (OCP).
 */
final class WriterRegistry
{
    /** @var array<string, TabularWriter> */
    private array $writers = [];

    public function register(string $format, TabularWriter $writer): void
    {
        $this->writers[$format] = $writer;
    }

    public function has(string $format): bool
    {
        return isset($this->writers[$format]);
    }

    public function get(string $format): TabularWriter
    {
        return $this->writers[$format]
            ?? throw new InvalidArgumentException("Unsupported export format [{$format}].");
    }
}
