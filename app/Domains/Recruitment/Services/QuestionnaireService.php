<?php

namespace App\Domains\Recruitment\Services;

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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class QuestionnaireService
{
    /** @var array<string, SectionDefinition> */
    private array $sections;

    public function __construct(
        private QuestionnaireRepositoryInterface $repository,
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
            abort(404, 'Questionnaire not found.');
        }

        return $questionnaire;
    }

    public function updateStatus(Questionnaire $questionnaire, string $status): Questionnaire
    {
        return $this->repository->updateStatus($questionnaire, $status);
    }

    public function updateOtp(Questionnaire $questionnaire, string $type, string $otp): Questionnaire
    {
        return $this->repository->updateOtp($questionnaire, $type, $otp);
    }

    public function verifyOtp(Questionnaire $questionnaire, string $type): Questionnaire
    {
        return $this->repository->verifyOtp($questionnaire, $type);
    }

    /**
     * Save a single section (structural validation — draft safe).
     */
    public function saveSection(Questionnaire $questionnaire, string $sectionKey, array $data): Questionnaire
    {
        $section = $this->getSection($sectionKey);

        // Validate with structural rules (nullable/format only)
        $validator = Validator::make(
            [$sectionKey => $data],
            $this->prefixRules($section->structuralRules(), $sectionKey),
        );

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
            $rules = $section->completionRules();

            if (empty($rules)) {
                continue;
            }

            $validator = Validator::make(
                [$key => $sectionData],
                $this->prefixRules($rules, $key),
            );

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
     * Prefix rule keys AND conditional rule field references with section key.
     *
     * Rules like required_if, required_unless, required_with, etc. reference
     * sibling fields. After wrapping data as [sectionKey => $data], those
     * references must also be prefixed.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function prefixRules(array $rules, string $prefix): array
    {
        $conditionalMethods = [
            'required_if',
            'required_unless',
            'required_with',
            'required_with_all',
            'required_without',
            'required_without_all',
        ];

        $prefixed = [];
        foreach ($rules as $field => $rule) {
            $prefixedField = "{$prefix}.{$field}";
            $prefixed[$prefixedField] = $this->prefixConditionalReferences($rule, $prefix, $conditionalMethods);
        }

        return $prefixed;
    }

    /**
     * Prefix field references inside conditional rules.
     *
     * @param  string[]  $conditionalMethods
     */
    private function prefixConditionalReferences(string $rule, string $prefix, array $conditionalMethods): string
    {
        // Handle pipe-separated rules: "nullable|required_if:field,value|..."
        $pipes = explode('|', $rule);
        $result = [];

        foreach ($pipes as $pipe) {
            $trimmed = trim($pipe);
            $colonPos = strpos($trimmed, ':');

            if ($colonPos !== false) {
                $method = substr($trimmed, 0, $colonPos);
                $params = substr($trimmed, $colonPos + 1);

                if (in_array($method, $conditionalMethods)) {
                    // First param is the field reference — prefix it
                    $paramParts = explode(',', $params, 2);
                    $paramParts[0] = "{$prefix}.{$paramParts[0]}";
                    $trimmed = $method.':'.implode(',', $paramParts);
                }
            }

            $result[] = $trimmed;
        }

        return implode('|', $result);
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
