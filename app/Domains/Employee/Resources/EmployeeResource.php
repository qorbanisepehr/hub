<?php

namespace App\Domains\Employee\Resources;

use App\Contracts\Authorization;
use App\Domains\Authorization\Services\FieldAccess;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $fields = [
            'id' => $this->id,
            'personnel_code' => $this->personnel_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'id_number' => $this->id_number,
            'marital_status' => $this->marital_status,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'employment_type' => $this->employment_type,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'employment_status' => $this->employment_status,
            'social_insurance_number' => $this->social_insurance_number,
            'section_social_insurance' => $this->section_social_insurance,
            'section_dependents' => $this->section_dependents,
            'section_document_inquiries' => $this->section_document_inquiries,
            'section_personal' => $this->section_personal,
            'section_contact_address' => $this->section_contact_address,
            'section_education' => $this->section_education,
            'section_work_experience' => $this->section_work_experience,
            'section_skills' => $this->section_skills,
            'section_training' => $this->section_training,
            'section_additional_info' => $this->section_additional_info,
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
            'capabilities' => $this->capabilities($request),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        $actor = $request->user();

        if ($actor !== null) {
            $fields = app(FieldAccess::class)->filter($actor, 'employee', $this->resource, $fields);
        }

        return $fields;
    }

    /**
     * Backend-authoritative capability set for the current actor and employee.
     * The frontend derives UI affordances (edit/delete/document actions) from
     * these instead of re-deriving them from roles or permission names.
     *
     * @return array<string, bool>
     */
    private function capabilities(Request $request): array
    {
        $actor = $request->user();

        if ($actor === null) {
            return [
                'view' => false,
                'edit' => false,
                'delete' => false,
                'documents_view' => false,
                'documents_upload' => false,
                'documents_delete' => false,
            ];
        }

        $authorization = app(Authorization::class);

        return [
            'view' => $authorization->can($actor, 'employee.view', $this->resource),
            'edit' => $authorization->can($actor, 'employee.update', $this->resource),
            'delete' => $authorization->can($actor, 'employee.delete', $this->resource),
            'documents_view' => $authorization->can($actor, 'employee.documents.view', $this->resource),
            'documents_upload' => $authorization->can($actor, 'employee.documents.upload', $this->resource),
            'documents_delete' => $authorization->can($actor, 'employee.documents.delete', $this->resource),
        ];
    }
}
