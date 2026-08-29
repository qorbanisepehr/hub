<?php

namespace App\Domains\Cv\Services;

use App\Domains\Cv\Enums\CvStatus;
use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Repositories\CvRepositoryInterface;
use App\Domains\Cv\Sections\AdditionalInfoSection;
use App\Domains\Cv\Sections\ContactInfoSection;
use App\Domains\Cv\Sections\PersonalInfoSection;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Questionnaire\Sections\EducationSection;
use App\Domains\Questionnaire\Sections\SkillsSection;
use App\Domains\Questionnaire\Sections\TrainingSection;
use App\Domains\Questionnaire\Sections\WorkExperienceSection;
use App\Support\MobileNumber;
use App\Support\Sections\SectionDefinition;
use App\Support\Sections\SectionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CvService extends SectionRegistry
{
    public function __construct(
        private CvRepositoryInterface $repository,
        private DocumentService $documentService,
        private QuestionnaireRepositoryInterface $questionnaireRepository,
    ) {
        parent::__construct();
    }

    protected function definitions(): array
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

        return [...$definitions, ...$shared];
    }

    protected function documentsSectionKey(): ?string
    {
        // CV documents are placed at the standalone 'documents' section.
        return 'documents';
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

    public function updateStatus(Cv $cv, CvStatus $status): Cv
    {
        return $this->repository->updateStatus($cv, $status->value);
    }

    /**
     * Save a single section (structural validation — draft/rejected safe).
     *
     * Editing a rejected CV flips it back to draft so it reads as "not
     * submitted" in the bank while the candidate reworks it; the rejection
     * history stays in the lifecycle.
     */
    public function saveSection(
        Cv $cv,
        string $sectionKey,
        array $data
    ): Cv {
        $section = $this->getSection($sectionKey);

        if (isset($data['mobile'])) {
            $data['mobile'] = MobileNumber::normalize($data['mobile']);
        }

        $validator = $section->validateData(
            $data,
            SectionDefinition::MODE_STRUCTURAL
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use (
            $cv,
            $section,
            $data
        ): Cv {
            $storage = $section->storage();
            $jsonbColumn = $storage['jsonb'] ?? null;

            // JSONB column gets the full payload (preserves existing behavior);
            // real columns below are the authoritative copy for shared fields.
            if ($jsonbColumn !== null) {
                $cv = $this->repository->updateSection(
                    $cv,
                    $jsonbColumn,
                    $data
                );
            }

            $realData = $this->extractRealFields(
                $data,
                $storage['real'] ?? []
            );

            if ($realData !== []) {
                $cv->update($realData);
            }

            $cv = $cv->fresh();

            if ($cv->isRejected()) {
                $cv = $this->updateStatus($cv, CvStatus::Draft);
            }

            // Every edit produces a new revision so the bank shows how many
            // times the candidate has reworked the CV.
            return $this->repository->incrementVersion($cv);
        });
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

        return $this->updateStatus($cv, CvStatus::Submitted);
    }

    /**
     * Approve the CV as the given user.
     */
    public function approve(Cv $cv, ?int $reviewedBy = null): Cv
    {
        $cv->recordLifecycleEvent([
            'event' => 'approved',
            'version' => $cv->version,
            'at' => now()->toISOString(),
            'by' => $reviewedBy,
        ]);

        $cv->update(['reviewed_by' => $reviewedBy]);

        return $this->updateStatus($cv, CvStatus::Approved);
    }

    /**
     * Reject the CV with a mandatory reason recorded in the lifecycle.
     *
     * Unlike the old draft-reset behaviour, a rejected CV keeps its
     * "rejected" status so it stays visible and labelled in the bank until
     * the candidate edits it again (which flips it back to draft).
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

        return $this->updateStatus($cv, CvStatus::Rejected);
    }

    /**
     * Build a draft questionnaire prefilled from a submitted or
     * approved CV. Creating the questionnaire approves the CV automatically
     * (a rejected CV can never reach the next step), recording the reviewer.
     */
    public function createQuestionnaireFromCv(Cv $cv, ?int $reviewedBy = null): Questionnaire
    {
        if (! $cv->isSubmitted() && ! $cv->isApproved()) {
            abort(422, __('cv.only_submitted_creatable'));
        }

        if (Questionnaire::where('cv_id', $cv->id)->exists()) {
            abort(422, __('cv.already_linked'));
        }

        if (! $cv->isApproved()) {
            $cv = $this->approve($cv, $reviewedBy);
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
            'id_number' => $personal['id_number'] ?? null,
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

        // email/mobile are committed to real columns (init + OTP verification),
        // so the authoritative values must drive completion validation even if
        // the JSONB copy was never written or has gone stale.
        $data['contact_info'] = array_merge(
            $data['contact_info'] ?? [],
            [
                'email' => $cv->email,
                'mobile' => $cv->mobile,
            ],
        );

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

    private function extractRealFields(
        array $data,
        array $realFields
    ): array {
        return array_intersect_key(
            $data,
            array_flip($realFields)
        );
    }
}
