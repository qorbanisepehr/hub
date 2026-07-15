<?php

namespace App\Domains\Employee\Resources;

use App\Domains\Employee\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'personnel_code' => $this->personnel_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'id_number' => $this->id_number,
            'marital_status' => $this->marital_status,
            'education_level' => $this->education_level,
            'education_field' => $this->education_field,
            'employment_type' => $this->employment_type,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'employment_status' => $this->employment_status,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'username' => $this->user->username,
                'active_role' => $this->user->activeRole
                    ? ['id' => $this->user->activeRole->id, 'display_name' => $this->user->activeRole->display_name]
                    : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
