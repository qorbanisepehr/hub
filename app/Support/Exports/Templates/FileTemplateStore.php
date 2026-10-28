<?php

namespace App\Support\Exports\Templates;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Where a Word template's bytes live.
 *
 * The path is {@see FileTemplate::filePath()} — derived from the key — and this
 * class is the only place that disk is named. Templates sit on the same private
 * disk as the export archives rather than in a second configured disk, because
 * they are the same kind of thing: minutes-derived output that must not be
 * reachable without going through the application.
 *
 * NEVER `public`. A template carries the board's letterhead and the shape of its
 * minutes, and a public disk serves both to anyone with the URL.
 *
 * Writing is last-writer-wins and reading refuses to guess: a template whose
 * file has gone is reported as missing rather than rendered from an empty
 * document, because an export that silently loses its layout is worse than one
 * that says which file it could not find.
 */
final class FileTemplateStore
{
    private function disk(): Filesystem
    {
        return Storage::disk((string) config('exports.disk'));
    }

    /** Absolute path, for the libraries that insist on one. */
    public function path(FileTemplate $template): string
    {
        return $this->disk()->path($template->filePath());
    }

    public function has(FileTemplate $template): bool
    {
        return $this->disk()->exists($template->filePath());
    }

    /**
     * The template's file as a readable stream, or null when it is gone.
     *
     * A row whose file has vanished is a state the library never creates — the
     * path is derived and the writes land in step — but a download must not
     * guess: null is the caller's signal to answer 404, the same honesty the
     * export path applies to a template nobody has.
     *
     * @return resource|null
     */
    public function readStream(FileTemplate $template)
    {
        if ($this->disk()->exists($template->filePath()) === false) {
            return null;
        }

        return $this->disk()->readStream($template->filePath());
    }

    /**
     * @throws \RuntimeException when the file cannot be written
     */
    public function write(FileTemplate $template, string $bytes): void
    {
        if ($this->disk()->put($template->filePath(), $bytes) === false) {
            throw new \RuntimeException(sprintf(
                'Could not write the template [%s].',
                $template->key(),
            ));
        }
    }

    public function delete(FileTemplate $template): void
    {
        $this->disk()->delete($template->filePath());
    }
}
