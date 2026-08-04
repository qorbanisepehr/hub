<?php

namespace App\Domains\Cv\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read int $id */
/** @property-read string $uuid */
/** @property-read string $status */
/** @property-read string $first_name */
/** @property-read string $last_name */
/** @property-read string $email */
/** @property-read string $mobile */
/** @property-read int $version */
/** @property-read array|null $section_personal */
/** @property-read array|null $section_contact_address */
/** @property-read array|null $section_education */
/** @property-read array|null $section_work_experience */
/** @property-read array|null $section_skills */
/** @property-read array|null $section_training */
/** @property-read array|null $section_additional_info */
/** @property-read array|null $lifecycle */
class CvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'version' => $this->version,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'personal_info' => $this->section_personal,
            'contact_info' => $this->section_contact_address,
            'education' => $this->section_education,
            'work_experience' => $this->section_work_experience,
            'skills' => $this->section_skills,
            'training' => $this->section_training,
            'additional_info' => $this->section_additional_info,
            'mobile_verified' => $this->isMobileVerified(),
            'email_verified' => $this->isEmailVerified(),
            'lifecycle' => $this->lifecycle,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
