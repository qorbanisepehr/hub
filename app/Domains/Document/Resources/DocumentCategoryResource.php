<?php

namespace App\Domains\Document\Resources;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentCategory */
class DocumentCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $requirements = app(QuestionnaireService::class)->getDocumentRequirements();
        $requirement = $requirements[$this->slug] ?? null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'parent_id' => $this->parent_id,
            'type' => $this->type,
            'requirement' => $requirement ? [
                'required' => $requirement['required'] ?? false,
                'max_files' => $requirement['max_files'] ?? null,
                'record_keys' => $requirement['record_keys'] ?? null,
                'min_file_size' => $requirement['min_file_size'] ?? null,
                'max_file_size' => $requirement['max_file_size'] ?? null,
                'mime_types' => $requirement['mime_types'] ?? null,
                'dimensions' => $requirement['dimensions'] ?? null,
            ] : null,
            'children' => DocumentCategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
