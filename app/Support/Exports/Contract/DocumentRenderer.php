<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\Document\DocumentSpec;

/**
 * The format-side port for document output (PDF/Word): turns a kernel
 * DocumentSpec into file bytes on the given stream. Separate from
 * TabularWriter by contract (LSP): writers stream row-by-row, renderers
 * must hold the whole document in memory, so the kernel routes document
 * formats here, never through a writer.
 */
interface DocumentRenderer
{
    /**
     * @param  resource  $stream  Writable stream the bytes are written to.
     */
    public function render(DocumentSpec $spec, $stream): void;

    public function contentType(): string;

    public function extension(): string;
}
