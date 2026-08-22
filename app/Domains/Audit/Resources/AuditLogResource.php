<?php

namespace App\Domains\Audit\Resources;

use App\Domains\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
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
            ],
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
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
