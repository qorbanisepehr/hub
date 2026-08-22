<?php

namespace App\Domains\Audit\Resources;

use App\Domains\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $actorUser = $this->whenLoaded('actorUser');

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event' => $this->event,
            'category' => $this->category,
            'actor' => [
                'type' => $this->actor_type,
                'id' => $this->actor_id,
                'name' => $actorUser?->name,
                'avatar_url' => $actorUser?->getServeAvatarUrl(),
                'display_name' => $actorUser ? ($this->getEmployeeDisplayName($actorUser) ?? $actorUser->name) : null,
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
                'old' => $this->decodeNestedJson($this->old_values),
                'new' => $this->decodeNestedJson($this->new_values),
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

    /**
     * Recursively decode JSON-encoded array values (e.g. section payloads stored
     * as strings by Eloquent attribute diffs) so the client receives real objects.
     */
    private function decodeNestedJson(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }

        if (is_array($value)) {
            return array_map([$this, 'decodeNestedJson'], $value);
        }

        return $value;
    }

    private function getEmployeeDisplayName(User $user): ?string
    {
        if (! $user->relationLoaded('employee')) {
            $user->load('employee:id,user_id,first_name,last_name');
        }

        $employee = $user->employee;

        if ($employee && ($employee->first_name || $employee->last_name)) {
            return trim("{$employee->first_name} {$employee->last_name}");
        }

        return null;
    }
}
