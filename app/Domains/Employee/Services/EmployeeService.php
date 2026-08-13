<?php

namespace App\Domains\Employee\Services;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Sections\AdditionalInfoSection;
use App\Domains\Employee\Sections\ContactInfoSection;
use App\Domains\Employee\Sections\EmploymentSection;
use App\Domains\Questionnaire\Sections\EducationSection;
use App\Domains\Questionnaire\Sections\PersonalInfoSection;
use App\Domains\Questionnaire\Sections\SkillsSection;
use App\Domains\Questionnaire\Sections\TrainingSection;
use App\Domains\Questionnaire\Sections\WorkExperienceSection;
use App\Support\MobileNumber;
use App\Support\Sections\SectionDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EmployeeService
{
    /** @var array<string, SectionDefinition> */
    private array $sections;

    public function __construct()
    {
        $this->registerSections();
    }

    private function registerSections(): void
    {
        // All questionnaire sections except job_request (applicant-preference
        // fields don't apply to existing employees). Definitions are reused
        // cross-domain to keep a single source of validation rules. Contact
        // info and additional info use employee-specific definitions because
        // their real-column ownership differs from the questionnaire's.
        $definitions = [
            PersonalInfoSection::class,
            ContactInfoSection::class,
            EmploymentSection::class,
            EducationSection::class,
            WorkExperienceSection::class,
            SkillsSection::class,
            TrainingSection::class,
            AdditionalInfoSection::class,
        ];

        foreach ($definitions as $class) {
            $section = new $class;
            $this->sections[$section->key()] = $section;
        }
    }

    /**
     * Merge per-category document requirements declared by the registered
     * sections. Employee documents are uploaded in a standalone 'documents'
     * step, so every requirement is placed at the documents section. This
     * keeps the Employee domain independent of the Questionnaire service
     * (only the shared section definitions are reused).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDocumentRequirements(): array
    {
        $requirements = [];

        foreach ($this->sections as $section) {
            foreach ($section->documentRequirements() as $slug => $requirement) {
                $requirements[$slug] = $requirement + ['section_key' => 'documents'];
            }
        }

        return $requirements;
    }

    /**
     * Create an employee with optional section data (structural validation —
     * draft safe, same as the questionnaire's per-section saves).
     *
     * @param  array<string, mixed>  $baseData
     * @param  array<string, mixed>  $sections
     */
    public function create(array $baseData, array $sections = []): Employee
    {
        $employee = Employee::create($baseData);

        foreach ($sections as $key => $data) {
            $this->saveSection($employee, $key, $data);
        }

        return $employee;
    }

    /**
     * Save a single section (structural validation — draft safe).
     */
    public function saveSection(Employee $employee, string $sectionKey, array $data): Employee
    {
        $section = $this->getSection($sectionKey);

        if (isset($data['mobile'])) {
            $data['mobile'] = MobileNumber::normalize($data['mobile']);
        }

        $validator = $section->validateData($data, SectionDefinition::MODE_STRUCTURAL);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if ($sectionKey === 'employment') {
            $this->assertPersonnelCodeUnique($employee, $data['personnel_code'] ?? null);
        }

        return DB::transaction(function () use (
            $employee,
            $section,
            $data
        ): Employee {
            $storage = $section->storage();

            $realFields = $storage['real'] ?? [];
            $jsonbColumn = $storage['jsonb'] ?? null;

            $realData = $this->extractRealFields(
                $data,
                $realFields
            );

            if ($realData !== []) {
                $employee->update($realData);
            }

            if ($jsonbColumn !== null) {
                $jsonbData = $this->extractJsonbData(
                    $data,
                    $realFields
                );

                $employee->update([
                    $jsonbColumn => $jsonbData,
                ]);
            }

            return $employee->fresh();
        });

    }

    /**
     * Submit the employee profile (completion validation across all sections).
     */
    public function submit(Employee $employee): Employee
    {
        $errors = $this->validateCompletion($employee);

        if (! empty($errors)) {
            $validator = Validator::make([], []);
            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
            throw new ValidationException($validator);
        }

        return $employee->fresh();
    }

    /**
     * Run completion validation against all registered sections.
     *
     * @return array<string, string[]>
     */
    public function validateCompletion(Employee $employee): array
    {
        $allData = $this->gatherAllData($employee);
        $allErrors = [];

        foreach ($this->sections as $key => $section) {
            if (empty($section->rulesFor(SectionDefinition::MODE_COMPLETION))) {
                continue;
            }

            $validator = $section->validateData($allData[$key] ?? [], SectionDefinition::MODE_COMPLETION);

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

    /**
     * @return string[]
     */
    public function getSectionKeys(): array
    {
        return array_keys($this->sections);
    }

    /**
     * Reject a personnel code that another employee already owns. The column is
     * NOT NULL and unique, so this turns what would be a DB constraint error
     * into a proper 422 validation response. Empty values are allowed here —
     * they are covered by the completion rules at submit time.
     */
    private function assertPersonnelCodeUnique(Employee $employee, mixed $personnelCode): void
    {
        if (! is_string($personnelCode) || $personnelCode === '') {
            return;
        }

        $exists = Employee::query()
            ->where('personnel_code', $personnelCode)
            ->whereKeyNot($employee->getKey())
            ->exists();

        if (! $exists) {
            return;
        }

        $validator = Validator::make([], []);
        $validator->errors()->add('employment.personnel_code', __('employee.validation.personnel_code_unique'));
        throw new ValidationException($validator);
    }

    /**
     * Extract the real-column values for a section from storage(). Empty
     * strings are normalized to null so nullable unique columns (email, mobile,
     * id_number) never collide.
     *
     * @param  array<string, mixed>  $data
     * @param  string[]  $realFields
     * @return array<string, mixed>
     */
    private function extractRealFields(array $data, array $realFields): array
    {
        $realData = array_intersect_key($data, array_flip($realFields));

        foreach ($realData as $field => $value) {
            $realData[$field] = is_string($value) && $value === '' ? null : $value;
        }

        return $realData;
    }

    /**
     * Gather the full section data for completion validation, merging the real
     * columns back into the JSONB remainder so rules see the complete object.
     *
     * @return array<string, mixed>
     */
    private function gatherAllData(Employee $employee): array
    {
        $data = [];

        foreach ($this->sections as $key => $section) {
            $storage = $section->storage();
            $jsonbColumn = $storage['jsonb'] ?? null;
            $sectionData = $jsonbColumn ? ($employee->{$jsonbColumn} ?? []) : [];

            foreach ($storage['real'] ?? [] as $field) {
                $value = $employee->{$field} ?? null;
                if ($value !== null) {
                    $sectionData[$field] = $value;
                }
            }

            $data[$key] = $sectionData;
        }

        return $data;
    }

    private function extractJsonbData(
        array $data,
        array $realFields
    ): array {
        if ($realFields === []) {
            return $data;
        }

        return array_diff_key(
            $data,
            array_flip($realFields)
        );
    }
}
