<?php

namespace App\Domains\Recruitment\Services;

use App\Domains\Document\Services\DocumentService;
use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Recruitment\SectionDefinitions\AdditionalInfoSection;
use App\Domains\Recruitment\SectionDefinitions\ContactInfoSection;
use App\Domains\Recruitment\SectionDefinitions\EducationSection;
use App\Domains\Recruitment\SectionDefinitions\JobRequestSection;
use App\Domains\Recruitment\SectionDefinitions\PersonalInfoSection;
use App\Domains\Recruitment\SectionDefinitions\SectionDefinition;
use App\Domains\Recruitment\SectionDefinitions\SkillsSection;
use App\Domains\Recruitment\SectionDefinitions\TrainingSection;
use App\Domains\Recruitment\SectionDefinitions\WorkExperienceSection;
use App\Support\MobileNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class QuestionnaireService
{
    /** @var array<string, SectionDefinition> */
    private array $sections;

    public function __construct(
        private QuestionnaireRepositoryInterface $repository,
        private DocumentService $documentService,
    ) {
        $this->registerSections();
    }

    private function registerSections(): void
    {
        $definitions = [
            PersonalInfoSection::class,
            ContactInfoSection::class,
            EducationSection::class,
            WorkExperienceSection::class,
            SkillsSection::class,
            TrainingSection::class,
            AdditionalInfoSection::class,
            JobRequestSection::class,
        ];

        foreach ($definitions as $class) {
            $section = new $class;
            $this->sections[$section->key()] = $section;
        }
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
            abort(404, __('recruitment.questionnaire.not_found'));
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
    public function saveSection(Questionnaire $questionnaire, string $sectionKey, array $data): Questionnaire
    {
        $section = $this->getSection($sectionKey);

        if (isset($data['mobile'])) {
            $data['mobile'] = MobileNumber::normalize($data['mobile']);
        }

        // Validate with structural rules (nullable/format only)
        $validator = $section->validateData($data, SectionDefinition::MODE_STRUCTURAL);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Store to JSONB column
        $this->repository->updateSection($questionnaire, $section->storage()['jsonb'], $data);

        // Update real columns if defined
        if (! empty($section->storage()['real'])) {
            $realData = $this->extractRealFields($data, $section->storage()['real']);
            if (! empty($realData)) {
                $questionnaire->update($realData);
            }
        }

        return $questionnaire->fresh();
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
     * Merge per-category document requirements declared by every section definition.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDocumentRequirements(): array
    {
        $requirements = [];

        foreach ($this->sections as $section) {
            foreach ($section->documentRequirements() as $slug => $requirement) {
                $requirements[$slug] = $requirement;
            }
        }

        return $requirements;
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
     * Get section definition by key.
     */
    public function getSection(string $key): SectionDefinition
    {
        if (! isset($this->sections[$key])) {
            throw new InvalidArgumentException("Unknown section: {$key}");
        }

        return $this->sections[$key];
    }

    /**
     * Get all section keys.
     *
     * @return string[]
     */
    public function getSectionKeys(): array
    {
        return array_keys($this->sections);
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

    /**
     * Extract real column values from section data.
     *
     * @param  array<string, mixed>  $data
     * @param  string[]  $realFields
     * @return array<string, mixed>
     */
    private function extractRealFields(array $data, array $realFields): array
    {
        return array_intersect_key($data, array_flip($realFields));
    }
}
