<?php

namespace App\Domains\Questionnaire\Services;

use App\Domains\Document\Services\DocumentService;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Questionnaire\Sections\AdditionalInfoSection;
use App\Domains\Questionnaire\Sections\ContactInfoSection;
use App\Domains\Questionnaire\Sections\EducationSection;
use App\Domains\Questionnaire\Sections\JobRequestSection;
use App\Domains\Questionnaire\Sections\PersonalInfoSection;
use App\Domains\Questionnaire\Sections\SkillsSection;
use App\Domains\Questionnaire\Sections\TrainingSection;
use App\Domains\Questionnaire\Sections\WorkExperienceSection;
use App\Support\MobileNumber;
use App\Support\Sections\SectionDefinition;
use App\Support\Sections\SectionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuestionnaireService extends SectionRegistry
{
    public function __construct(
        private QuestionnaireRepositoryInterface $repository,
        private DocumentService $documentService,
    ) {
        parent::__construct();
    }

    protected function definitions(): array
    {
        return [
            PersonalInfoSection::class,
            ContactInfoSection::class,
            EducationSection::class,
            WorkExperienceSection::class,
            SkillsSection::class,
            TrainingSection::class,
            AdditionalInfoSection::class,
            JobRequestSection::class,
        ];
    }

    public function create(array $baseData): Questionnaire
    {
        return $this->repository->create($baseData);
    }

    public function findByUuid(string $uuid): ?Questionnaire
    {
        return $this->repository->findByUuid($uuid);
    }

    public function findByUuidOrFail(string $uuid): Questionnaire
    {
        $questionnaire = $this->repository->findByUuid($uuid);

        if (! $questionnaire) {
            abort(404, __('questionnaire.questionnaire.not_found'));
        }

        return $questionnaire;
    }

    public function updateStatus(Questionnaire $questionnaire, string $status): Questionnaire
    {
        return $this->repository->updateStatus($questionnaire, $status);
    }

    /**
     * Save a single section (structural validation — draft safe).
     */
    public function saveSection(
        Questionnaire $questionnaire,
        string $sectionKey,
        array $data
    ): Questionnaire {
        $section = $this->getSection($sectionKey);

        if (isset($data['mobile'])) {
            $data['mobile'] = MobileNumber::normalize($data['mobile']);
        }

        // Validate with structural rules (nullable/format only)
        $validator = $section->validateData($data, SectionDefinition::MODE_STRUCTURAL);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use (
            $questionnaire,
            $section,
            $data
        ): Questionnaire {
            $storage = $section->storage();
            $jsonbColumn = $storage['jsonb'] ?? null;

            // JSONB column gets the full payload (preserves existing behavior);
            // real columns below are the authoritative copy for shared fields.
            if ($jsonbColumn !== null) {
                $this->repository->updateSection(
                    $questionnaire,
                    $jsonbColumn,
                    $data
                );
            }

            // Update real columns if defined
            $realData = $this->extractRealFields($data, $storage['real'] ?? []);

            if ($realData !== []) {
                $questionnaire->update($realData);
            }

            return $questionnaire->fresh();
        });
    }

    private function extractRealFields(
        array $data,
        array $realFields
    ): array {
        return array_intersect_key(
            $data,
            array_flip($realFields)
        );
    }

    /**
     * Submit questionnaire (completion validation across all sections).
     */
    public function submit(Questionnaire $questionnaire): Questionnaire
    {
        $errors = $this->validateCompletion($questionnaire);
        $errors = array_merge($errors, $this->documentService->validateRequirements($questionnaire, $this->getDocumentRequirements()));

        if (! empty($errors)) {
            $validator = Validator::make([], []);
            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
            throw new ValidationException($validator);
        }

        return $this->repository->updateStatus($questionnaire, 'submitted');
    }

    /**
     * Run completion validation against all sections.
     *
     * @return array<string, string[]>
     */
    public function validateCompletion(Questionnaire $questionnaire): array
    {
        $allData = $this->gatherAllData($questionnaire);
        $allErrors = [];

        foreach ($this->sections as $key => $section) {
            $sectionData = $allData[$key] ?? null;

            if (empty($section->rulesFor(SectionDefinition::MODE_COMPLETION))) {
                continue;
            }

            $validator = $section->validateData($sectionData ?? [], SectionDefinition::MODE_COMPLETION);

            if ($validator->fails()) {
                $allErrors = array_merge($allErrors, $validator->errors()->toArray());
            }
        }

        return $allErrors;
    }

    /**
     * Gather all section data from questionnaire for completion validation.
     *
     * @return array<string, mixed>
     */
    private function gatherAllData(Questionnaire $questionnaire): array
    {
        $data = [];

        foreach ($this->sections as $key => $section) {
            $storage = $section->storage();
            $jsonbColumn = $storage['jsonb'] ?? null;
            $data[$key] = $jsonbColumn ? ($questionnaire->{$jsonbColumn} ?? null) : null;
        }

        return $data;
    }
}
