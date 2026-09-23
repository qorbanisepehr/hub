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
            'pagination' => [
                'page' => $this->resource->page,
                'per_page' => $this->resource->perPage,
                'total' => $this->resource->total,
                'has_more' => $this->resource->hasMore,
            ],
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
            'sender_name' => $item->senderName,
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
}
