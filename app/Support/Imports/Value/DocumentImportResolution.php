<?php

namespace App\Support\Imports\Value;

/**
 * The outcome of resolving one filename: accepted onto a target, or
 * rejected (orphan) with a stable reason code plus a human message the
 * preview renders ("no meeting on that date", …). The parsed parts are kept
 * so a rejected row still shows what the pattern extracted and why it
 * failed.
 */
final class DocumentImportResolution
{
    /**
     * @param  array<string, string>  $details  Parsed filename parts preserved for the preview.
     */
    private function __construct(
        public readonly ?DocumentImportTarget $target,
        public readonly ?string $reasonCode,
        public readonly ?string $reason,
        public readonly array $details = [],
    ) {}

    public static function accepted(DocumentImportTarget $target): self
    {
        return new self($target, null, null);
    }

    /**
     * @param  array<string, string>  $details
     */
    public static function rejected(string $reasonCode, string $reason, array $details = []): self
    {
        return new self(null, $reasonCode, $reason, $details);
    }

    public function isRejected(): bool
    {
        return $this->target === null;
    }
}
