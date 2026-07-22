<?php

namespace App\Domains\Recruitment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read int $id */
/** @property-read string $uuid */
/** @property-read string $status */
/** @property-read string $first_name */
/** @property-read string $last_name */
/** @property-read string $email */
/** @property-read string $mobile */
/** @property-read array|null $personal_info */
/** @property-read array|null $education */
/** @property-read array|null $work_experience */
/** @property-read array|null $skills */
/** @property-read array|null $training */
/** @property-read array|null $additional_info */
/** @property-read array|null $job_request */
/** @property-read array|null $review */
/** @property-read bool $mobile_verified */
/** @property-read bool $email_verified */
class QuestionnaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'personal_info' => $this->personal_info,
            'education' => $this->education,
            'work_experience' => $this->work_experience,
            'skills' => $this->skills,
            'training' => $this->training,
            'additional_info' => $this->additional_info,
            'job_request' => $this->job_request,
            'review' => $this->review,
            'mobile_verified' => $this->isMobileVerified(),
            'email_verified' => $this->isEmailVerified(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
