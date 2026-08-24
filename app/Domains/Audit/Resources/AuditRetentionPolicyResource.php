<?php

namespace App\Domains\Audit\Resources;

use App\Domains\Audit\Models\AuditRetentionPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditRetentionPolicy */
class AuditRetentionPolicyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'event' => $this->event,
            'retention_days' => $this->retention_days,
            'archive_after_days' => $this->archive_after_days,
            'archive_enabled' => $this->archive_enabled,
            'delete_after_archive' => $this->delete_after_archive,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
