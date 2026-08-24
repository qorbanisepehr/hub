<?php

namespace App\Domains\Cv\Resources;

use App\Contracts\Authorization;
use App\Domains\Cv\Enums\CvStatus;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @property-read int $id */
/** @property-read string $uuid */
/** @property-read CvStatus $status */
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
            'status' => $this->status->value,
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
            'documents' => $this->documentsPayload(),
            'resume_document' => $this->resumeDocumentPayload(),
            'questionnaire' => $this->questionnairePayload(),
            'reviewer' => $this->reviewer
                ? $this->reviewerSummary($this->reviewer)
                : null,
            'lifecycle' => $this->enrichLifecycle($this->lifecycle),
            'capabilities' => $this->capabilities($request),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Attach the acting user's name and active role to every lifecycle event
     * so the client can render a readable timeline without extra lookups.
     *
     * @param  array<int, array<string, mixed>>|null  $lifecycle
     * @return array<int, array<string, mixed>>|null
     */
    private function enrichLifecycle(?array $lifecycle): ?array
    {
        if (empty($lifecycle)) {
            return $lifecycle;
        }

        $users = $this->loadLifecycleUsers($lifecycle);

        return array_map(function (array $event) use ($users): array {
            $event['by_user'] = isset($event['by'], $users[$event['by']])
                ? $this->reviewerSummary($users[$event['by']])
                : null;

            return $event;
        }, $lifecycle);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lifecycle
     * @return Collection<int, User>
     */
    private function loadLifecycleUsers(array $lifecycle): Collection
    {
        $userIds = collect($lifecycle)
            ->pluck('by')
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->with(['activeRole', 'employee'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array{id: int, name: string, role: string|null, employee: array<string, mixed>|null}
     */
    private function reviewerSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->activeRole?->display_name ?? $user->activeRole?->name ?? null,
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'first_name' => $user->employee->first_name,
                'last_name' => $user->employee->last_name,
                'personnel_code' => $user->employee->personnel_code,
            ] : null,
        ];
    }

    /**
     * The CV documents are only embedded when the caller eager-loaded
     * `documentUsages.document` (the bank index/show endpoints do). This keeps
     * the candidate-facing resource free of the extra payload.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentsPayload(): array
    {
        if (! $this->relationLoaded('documentUsages')) {
            return [];
        }

        return $this->documentUsages
            ->filter(fn (DocumentUsage $usage) => $usage->document !== null)
            ->map(fn (DocumentUsage $usage) => $this->documentPayload($usage))
            ->values()
            ->all();
    }

    /**
     * The linked questionnaire (uuid + status), exposed only when
     * the caller eager-loaded the `questionnaire` relation (the bank detail
     * endpoint does). Used by the review page to offer a share action and to
     * hide CV actions that no longer apply once the CV moved to a
     * questionnaire.
     *
     * @return array{uuid: string, status: string}|null
     */
    private function questionnairePayload(): ?array
    {
        if (! $this->relationLoaded('questionnaire') || ! $this->questionnaire) {
            return null;
        }

        return [
            'uuid' => $this->questionnaire->uuid,
            'status' => $this->questionnaire->status,
        ];
    }

    /**
     * The resume document (first usage under the `resume` category), used by
     * the bank list for the "download resume" action.
     *
     * @return array<string, mixed>|null
     */
    private function resumeDocumentPayload(): ?array
    {
        if (! $this->relationLoaded('documentUsages')) {
            return null;
        }

        $resume = $this->documentUsages
            ->filter(fn (DocumentUsage $usage) => $usage->document !== null && $usage->document->category?->slug === 'resume')
            ->first();

        return $resume ? $this->documentPayload($resume) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(DocumentUsage $usage): array
    {
        $document = $usage->document;
        $names = app(DocumentService::class)->structureNames($document, $usage);

        return [
            'id' => $document->id,
            'usage_id' => $usage->id,
            'uuid' => $document->uuid,
            'structure_name' => $names['name'],
            'structure_name_slug' => $names['slug'],
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'category' => $document->category ? [
                'id' => $document->category->id,
                'name' => $document->category->name,
                'slug' => $document->category->slug,
            ] : null,
            'section_key' => $usage->section_key,
            'field_key' => $usage->field_key,
            'notes' => $usage->metadata['notes'] ?? null,
            'metadata' => $usage->metadata ?? [],
            'url' => route('cv.documents.serve', ['uuid' => $document->uuid], false),
            'download_url' => route(
                'cv.documents.serve',
                ['uuid' => $document->uuid, 'download' => 1],
                false,
            ),
        ];
    }

    /**
     * Backend-authoritative capability set for the current actor and CV. The
     * frontend derives review UI affordances (approve/reject/create-questionnaire)
     * from these instead of re-deriving them from permission names.
     *
     * @return array<string, bool>
     */
    private function capabilities(Request $request): array
    {
        $actor = $request->user();

        if ($actor === null) {
            return [
                'approve' => false,
                'reject' => false,
                'create_questionnaire' => false,
            ];
        }

        $authorization = app(Authorization::class);

        return [
            'approve' => $authorization->can($actor, 'cv.approve', $this->resource),
            'reject' => $authorization->can($actor, 'cv.reject', $this->resource),
            'create_questionnaire' => $authorization->can($actor, 'cv.create-questionnaire', $this->resource),
        ];
    }
}
