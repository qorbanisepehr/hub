<?php

namespace App\Domains\Audit\Resources;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event' => $this->event,
            'category' => $this->category,
            'actor' => [
                'type' => $this->actor_type,
                'id' => $this->actor_id,
                'role' => [
                    'id' => $this->actor_role_id,
                    'name' => $this->actor_role_name,
                ],
            ],
            'subject' => [
                'type' => $this->subject_type,
                'id' => $this->subject_id,
                'snapshot' => $this->subject_snapshot,
            ],
            'description' => $this->description,
            'changes' => [
                'old' => $this->old_values,
                'new' => $this->new_values,
            ],
            'metadata' => $this->metadata,
            'request' => [
                'ip_address' => $this->ip_address,
                'user_agent' => $this->user_agent,
                'url' => $this->url,
                'method' => $this->method,
                'request_id' => $this->request_id,
                'trace_id' => $this->trace_id,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
