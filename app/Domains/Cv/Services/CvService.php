<?php

namespace App\Domains\Cv\Services;

use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Repositories\CvRepositoryInterface;
use App\Domains\Cv\SectionDefinitions\AdditionalInfoSection;
use App\Domains\Cv\SectionDefinitions\ContactInfoSection;
use App\Domains\Cv\SectionDefinitions\PersonalInfoSection;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Recruitment\SectionDefinitions\EducationSection;
use App\Domains\Recruitment\SectionDefinitions\SectionDefinition;
use App\Domains\Recruitment\SectionDefinitions\SkillsSection;
use App\Domains\Recruitment\SectionDefinitions\TrainingSection;
use App\Domains\Recruitment\SectionDefinitions\WorkExperienceSection;
use App\Support\MobileNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CvService
{
    /** @var array<string, SectionDefinition> */
    private array $sections;

    public function __construct(
        private CvRepositoryInterface $repository,
        private DocumentService $documentService,
        private QuestionnaireRepositoryInterface $questionnaireRepository,
    ) {
        $this->registerSections();
    }

    private function registerSections(): void
    {
        // CV-specific definitions (slim field set / CV document requirements).
        $definitions = [
            PersonalInfoSection::class,
            ContactInfoSection::class,
            AdditionalInfoSection::class,
        ];

        // Sections identical to the questionnaire's are reused cross-domain to
        // avoid duplicating their rules (documented DRY pattern).
        $shared = [
            EducationSection::class,
            WorkExperienceSection::class,
            SkillsSection::class,
            TrainingSection::class,
        ];

        foreach ([...$definitions, ...$shared] as $class) {
            $section = new $class;
            $this->sections[$section->key()] = $section;
        }
    }

    public function create(array $baseData): Cv
    {
        return $this->repository->create($baseData);
    }

    public function findByUuid(string $uuid): ?Cv
    {
        return $this->repository->findByUuid($uuid);
    }

    public function findByUuidOrFail(string $uuid): Cv
    {
        $cv = $this->repository->findByUuid($uuid);

        if (! $cv) {
            abort(404, __('cv.not_found'));
        }

        return $cv;
    }

    public function updateStatus(Cv $cv, string $status): Cv
    {
        return $this->repository->updateStatus($cv, $status);
    }

    /**
     * Save a single section (structural validation — draft safe).
     */
    public function saveSection(Cv $cv, string $sectionKey, array $data): Cv
    {
        $section = $this->getSection($sectionKey);

        if (isset($data['mobile'])) {
            $data['mobile'] = MobileNumber::normalize($data['mobile']);
        }

        $validator = $section->validateData($data, SectionDefinition::MODE_STRUCTURAL);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $this->repository->updateSection($cv, $section->storage()['jsonb'], $data);

        if (! empty($section->storage()['real'])) {
            $realData = $this->extractRealFields($data, $section->storage()['real']);
            if (! empty($realData)) {
                $cv->update($realData);
            }
        }

        return $cv->fresh();
    }

    /**
     * Submit the CV (completion validation across all sections + documents).
     * Records a submission snapshot in the lifecycle history.
     */
    public function submit(Cv $cv): Cv
    {
        $errors = $this->validateCompletion($cv);
        $errors = array_merge($errors, $this->documentService->validateRequirements($cv, $this->getDocumentRequirements()));

        if (! empty($errors)) {
            $validator = Validator::make([], []);
            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
            throw new ValidationException($validator);
        }

        $cv->recordLifecycleEvent([
            'event' => 'submitted',
            'version' => $cv->version,
            'at' => now()->toISOString(),
            'snapshot' => $this->snapshot($cv),
        ]);

        return $this->repository->updateStatus($cv, 'submitted');
    }

    /**
     * Mark the CV as reviewed by the given user.
     */
    public function review(Cv $cv, ?int $reviewedBy = null): Cv
    {
        $cv->recordLifecycleEvent([
            'event' => 'reviewed',
            'version' => $cv->version,
            'at' => now()->toISOString(),
            'by' => $reviewedBy,
        ]);

        $cv->update(['reviewed_by' => $reviewedBy]);

        return $this->repository->updateStatus($cv, 'reviewed');
    }

    /**
     * Send the CV back to draft with a mandatory reason recorded in the lifecycle.
     */
    public function reject(Cv $cv, string $reason, ?int $reviewedBy = null): Cv
    {
        $cv->recordLifecycleEvent([
            'event' => 'rejected',
            'version' => $cv->version,
            'at' => now()->toISOString(),
            'by' => $reviewedBy,
            'reason' => $reason,
        ]);

        return $this->repository->updateStatus($cv, 'draft');
    }

    /**
     * Build a draft recruitment questionnaire prefilled from a submitted CV.
     */
    public function createQuestionnaireFromCv(Cv $cv): Questionnaire
    {
        if (! $cv->isSubmitted()) {
            abort(422, __('cv.only_submitted_creatable'));
        }

        if (Questionnaire::where('cv_id', $cv->id)->exists()) {
            abort(422, __('cv.already_linked'));
        }

        $personal = $cv->getSection('personal') ?? [];

        return $this->questionnaireRepository->create([
            'cv_id' => $cv->id,
            'first_name' => $cv->first_name,
            'last_name' => $cv->last_name,
            'email' => $cv->email,
            'mobile' => $cv->mobile,
            'gender' => $personal['gender'] ?? null,
            'birth_date' => $personal['birth_date'] ?? null,
            'marital_status' => $personal['marital_status'] ?? null,
            'national_id' => $personal['national_id'] ?? null,
            'section_personal' => $personal,
            'section_contact_address' => $cv->section_contact_address ?? [],
            'section_education' => $cv->section_education ?? [],
            'section_work_experience' => $cv->section_work_experience ?? [],
            'section_skills' => $cv->section_skills ?? [],
            'section_training' => $cv->section_training ?? [],
            'section_additional_info' => $cv->section_additional_info ?? [],
        ]);
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
    public function validateCompletion(Cv $cv): array
    {
        $allData = $this->gatherAllData($cv);
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

    public function getSection(string $key): SectionDefinition
    {
        if (! isset($this->sections[$key])) {
            throw new InvalidArgumentException("Unknown section: {$key}");
        }

        return $this->sections[$key];
    }

    /** @return string[] */
    public function getSectionKeys(): array
    {
        return array_keys($this->sections);
    }

    /**
     * Gather all section data from the CV for completion validation.
     *
     * @return array<string, mixed>
     */
    private function gatherAllData(Cv $cv): array
    {
        $data = [];

        foreach ($this->sections as $key => $section) {
            $storage = $section->storage();
            $jsonbColumn = $storage['jsonb'] ?? null;
            $data[$key] = $jsonbColumn ? ($cv->{$jsonbColumn} ?? null) : null;
        }

        return $data;
    }

    /**
     * Capture the submitted state: identity columns + every section.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Cv $cv): array
    {
        return [
            'first_name' => $cv->first_name,
            'last_name' => $cv->last_name,
            'email' => $cv->email,
            'mobile' => $cv->mobile,
            'sections' => $this->gatherAllData($cv),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  string[]  $realFields
     * @return array<string, mixed>
     */
    private function extractRealFields(array $data, array $realFields): array
    {
        return array_intersect_key($data, array_flip($realFields));
    }
}
