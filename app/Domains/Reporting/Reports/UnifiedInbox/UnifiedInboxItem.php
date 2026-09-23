<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

use Carbon\CarbonImmutable;

/**
 * The only inbox vocabulary Reporting is allowed to see (V2 §13): whatever
 * an external system calls its letter, request, or task dies at that
 * integration's mapper — merged items speak this shape. `externalReference`
 * keeps the origin identity ("rahkaran:123456", V2 §24) so the client can
 * link back through the source's gateway-provided URL (V2 §25).
 */
final readonly class UnifiedInboxItem
{
    public function __construct(
        /** Stable machine key of the source, e.g. "automation". */
        public string $source,
        /** Sender display name in the external system. */
        public string $senderName,
        /** Document/reference number in the external system's vocabulary. */
        public string $documentNumber,
        public string $title,
        /** Document type label already translated by the mapper (e.g. "نامه", "درخواست"). */
        public string $documentType,
        public CarbonImmutable $receivedAt,
        /** Cartable action bucket, e.g. "action_required" / "signature". */
        public string $cartableAction,
        /** Unified status vocabulary: "unseen" | "seen" | "done". */
        public string $status,
        /** Origin identity "system:externalId" (V2 §24). */
        public string $externalReference,
        /** Deep link into the source system, when the source provides one. */
        public ?string $externalUrl = null,
    ) {}
}
