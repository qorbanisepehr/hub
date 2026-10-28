<?php

namespace App\Domains\Reporting\Resources;

use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxItem;
use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * HTTP contract for the unified inbox report (V2 §17): item fields named
 * for the client's table (کارتابل sample), per-source status map, and the
 * pagination block.
 *
 * @property UnifiedInboxResult $resource
 */
class UnifiedInboxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => array_map(
                fn (UnifiedInboxItem $item): array => $this->item($item),
                $this->resource->items,
            ),
            'report' => $this->resource->report,
            'generated_at' => $this->resource->generatedAt->toIso8601String(),
            'meta' => $this->resource->meta,
            'sources' => (object) $this->resource->sources,
            'metadata' => (object) $this->resource->metadata,
            'warnings' => $this->resource->warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(UnifiedInboxItem $item): array
    {
        return [
            'source' => $item->source,
            'sender' => $this->sender($item),
            'document_number' => $item->documentNumber,
            'title' => $item->title,
            'document_type' => $item->documentType,
            'received_at' => $item->receivedAt->toIso8601String(),
            'cartable_action' => $item->cartableAction,
            'status' => $item->status,
            'external_reference' => $item->externalReference,
            'external_url' => $item->externalUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sender(UnifiedInboxItem $item): array
    {
        $sender = $item->sender;

        if ($sender === null) {
            return [
                'matched' => false,
                'display_name' => $item->senderName,
                'username' => null,
                'avatar_url' => null,
                'org_role' => null,
                'user_id' => null,
            ];
        }

        return [
            'matched' => $sender->matched,
            'display_name' => $sender->displayName,
            'username' => $sender->username,
            'avatar_url' => $sender->avatarUrl,
            'org_role' => $sender->orgRole,
            'user_id' => $sender->userId,
            'external_name' => $sender->matched ? null : $sender->displayName,
        ];
    }
}
