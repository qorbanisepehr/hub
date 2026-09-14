<?php

namespace App\Support\Exports\Value;

/**
 * Output of the export kernel: either a path on disk (queued exports, M1+)
 * or a callback that streams bytes directly to the HTTP response. The
 * service never buffers the whole file in memory.
 */
final class ExportFile
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mimeType,
        /** @var resource|null Absolute stream resource for streaming downloads. */
        public $stream = null,
        public readonly ?string $path = null,
    ) {}

    public static function fromPath(string $path, string $filename, string $mimeType): self
    {
        return new self(filename: $filename, mimeType: $mimeType, path: $path);
    }

    /**
     * Copy the produced bytes to a target stream (e.g. php://output inside
     * streamDownload), rewinding first since the writer leaves the pointer
     * at the end.
     */
    public function copyTo($target): void
    {
        if ($this->stream === null) {
            throw new \LogicException('This export has no stream to copy.');
        }

        rewind($this->stream);
        stream_copy_to_stream($this->stream, $target);
    }
}
