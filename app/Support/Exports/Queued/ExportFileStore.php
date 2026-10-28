<?php

namespace App\Support\Exports\Queued;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Where a finished archive waits for the person who asked for it.
 *
 * Built synchronously, a bundle is rendered, zipped and streamed inside one
 * request — which holds a worker for the whole build and gives the caller
 * nothing to do but wait. Anything larger is built by a job and lives here
 * until it is downloaded or expires.
 *
 * One directory per archive, holding the file under its own name and an
 * expiry stamp beside it. That shape is chosen so pruning is a directory scan
 * with no index to fall out of step: there is no metadata table that could
 * disagree with what is actually on disk, and deleting an expired archive is
 * deleting a directory rather than reconciling rows against it. A sidecar file
 * rather than an expiry encoded in the path, because the path is the token the
 * caller holds and it should not carry a timestamp somebody could edit.
 *
 * Knows nothing about what is in an archive, who asked for it, or whether they
 * are still allowed to have it. That is the caller's business, and a store that
 * guessed would be a second, weaker authorization.
 */
final class ExportFileStore
{
    /** Written next to each archive; the only thing pruning reads. */
    private const EXPIRY_FILE = '.expires-at';

    public function __construct(
        private readonly string $disk,
        private readonly string $directory,
        private readonly int $ttlHours,
    ) {}

    /**
     * Claim a token for an archive that is being built.
     *
     * The token exists before there is a file, which is what lets a queued
     * bundle be identified — and stopped — while it is still being rendered. It
     * is created here rather than at download time for exactly that reason.
     */
    public function issueToken(): string
    {
        return bin2hex(random_bytes(20));
    }

    public function expiryFor(string $token): Carbon
    {
        return now()->addHours($this->ttlHours);
    }

    /**
     * Move a built archive under a token, ready to be downloaded.
     */
    public function put(string $token, string $absolutePath, string $filename): void
    {
        $disk = $this->disk();

        // A token is single-use. Anything already under it is a previous
        // attempt that failed to clean up, and overwriting it is both the
        // tidiest outcome and the safe one — a stale archive for the same token
        // is a file nobody asked for and cannot tell apart from the real one.
        $this->delete($token);

        $disk->makeDirectory($this->directoryFor($token));
        $disk->put(
            $this->directoryFor($token).'/'.$this->safeName($filename),
            fopen($absolutePath, 'r'),
        );
        $disk->put($this->expiryPathFor($token), $this->expiryFor($token)->toIso8601String());
    }

    /**
     * The stored archive's path and name, or null when the token is unknown or
     * has expired.
     *
     * Null for both rather than one and the other: a caller cannot tell "never
     * existed" from "too late", and it has no reason to — both are answered the
     * same way, by telling the reader the archive is gone.
     *
     * @return array{path: string, filename: string}|null
     */
    public function find(string $token): ?array
    {
        if (! $this->isValidToken($token)) {
            return null;
        }

        $disk = $this->disk();
        $expiryPath = $this->expiryPathFor($token);

        if (! $disk->exists($expiryPath)) {
            return null;
        }

        try {
            $expiresAt = Carbon::parse((string) $disk->get($expiryPath));
        } catch (\Throwable) {
            // An unreadable stamp is treated as expired rather than as valid:
            // a file whose age cannot be established is not one to hand out.
            $this->delete($token);

            return null;
        }

        if ($expiresAt->isPast()) {
            $this->delete($token);

            return null;
        }

        $filename = $this->storedFilename($token);

        if ($filename === null) {
            return null;
        }

        return [
            'path' => $disk->path($this->directoryFor($token).'/'.$filename),
            'filename' => $filename,
        ];
    }

    public function delete(string $token): void
    {
        if (! $this->isValidToken($token)) {
            return;
        }

        $this->disk()->deleteDirectory($this->directoryFor($token));
    }

    /**
     * Delete every archive past its expiry. Returns how many went.
     *
     * Safe to run on a schedule and at the same time as a download, because
     * deleting an archive somebody is reading only ends that download, and the
     * two cannot corrupt each other.
     */
    public function pruneExpired(): int
    {
        $disk = $this->disk();

        if (! $disk->exists($this->directory)) {
            return 0;
        }

        $removed = 0;

        foreach ($disk->directories($this->directory) as $directory) {
            $token = basename($directory);
            $expiryPath = $this->directory.'/'.$token.'/'.self::EXPIRY_FILE;

            if (! $disk->exists($expiryPath)) {
                continue;
            }

            try {
                $expiresAt = Carbon::parse((string) $disk->get($expiryPath));
            } catch (\Throwable) {
                $expiresAt = now()->subSecond();
            }

            if ($expiresAt->isPast()) {
                $disk->deleteDirectory($directory);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * The name the archive was stored under, or null when it is not there.
     *
     * Read back off the disk rather than kept in the expiry stamp, so the two
     * cannot disagree about what a token holds.
     */
    private function storedFilename(string $token): ?string
    {
        $disk = $this->disk();
        $directory = $this->directoryFor($token);

        foreach ($disk->files($directory) as $file) {
            $name = basename($file);

            if ($name !== self::EXPIRY_FILE) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Tokens are hex from `random_bytes`, and nothing else is a token. Checked
     * before the value reaches a path, because a token is a caller-supplied
     * string and a path built from one unexamined is a way out of the export
     * directory.
     */
    private function isValidToken(string $token): bool
    {
        return preg_match('/^[0-9a-f]{40}$/', $token) === 1;
    }

    private function directoryFor(string $token): string
    {
        return $this->directory.'/'.$token;
    }

    private function expiryPathFor(string $token): string
    {
        return $this->directoryFor($token).'/'.self::EXPIRY_FILE;
    }

    /**
     * A stored name is a name we will serve back in a header, so anything that
     * could steer a header — a slash, a newline — is removed rather than
     * escaped. An export's own filename is already ours; a token's is not.
     */
    private function safeName(string $filename): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($filename)) ?? 'export';

        return $safe === '' ? 'export' : $safe;
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->disk);
    }
}
