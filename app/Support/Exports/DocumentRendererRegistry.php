<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\DocumentRenderer;
use InvalidArgumentException;

/**
 * Format name → document renderer (pdf, docx). The sibling of
 * WriterRegistry for the in-memory document half of the kernel; adding a
 * document format means registering a renderer, never editing the services.
 */
final class DocumentRendererRegistry
{
    /** @var array<string, DocumentRenderer> */
    private array $renderers = [];

    public function register(string $format, DocumentRenderer $renderer): void
    {
        $this->renderers[$format] = $renderer;
    }

    public function has(string $format): bool
    {
        return isset($this->renderers[$format]);
    }

    public function get(string $format): DocumentRenderer
    {
        return $this->renderers[$format]
            ?? throw new InvalidArgumentException("Unsupported document format [{$format}].");
    }
}
