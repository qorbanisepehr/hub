<?php

namespace App\Domains\Employee\Services;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\SectionDefinitions\EmploymentSection;
use App\Domains\Recruitment\SectionDefinitions\AdditionalInfoSection;
use App\Domains\Recruitment\SectionDefinitions\ContactInfoSection;
use App\Domains\Recruitment\SectionDefinitions\EducationSection;
use App\Domains\Recruitment\SectionDefinitions\PersonalInfoSection;
use App\Domains\Recruitment\SectionDefinitions\SectionDefinition;
use App\Domains\Recruitment\SectionDefinitions\SkillsSection;
use App\Domains\Recruitment\SectionDefinitions\TrainingSection;
use App\Domains\Recruitment\SectionDefinitions\WorkExperienceSection;
use App\Support\MobileNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EmployeeService
{
    /**
     * Fields that live on the employee's real columns instead of the JSONB
     * section. The questionnaire/CV persist the full object to JSONB, but the
     * employee already owned these columns, so merging them (single source of
     * truth) avoids storing the same value twice. Keyed by section key; value
     * maps the section field name to the employee column name.
     *
     * @var array<string, array<string, string>>
     */
    private const REAL_FIELD_MAP = [
        'personal_info' => [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'national_id' => 'id_number',
            'gender' => 'gender',
            'birth_date' => 'birth_date',
            'marital_status' => 'marital_status',
        ],
        'contact_info' => [
            'email' => 'email',
            'mobile' => 'mobile',
        ],
        'employment' => [
            'personnel_code' => 'personnel_code',
            'employment_type' => 'employment_type',
            'hire_date' => 'hire_date',
            'employment_status' => 'employment_status',
        ],
    ];

    /**
     * Section keys that are never written to the JSONB column because they are
     * owned by a real column (see REAL_FIELD_MAP).
     *
     * @var array<string, string[]>
     */
    private const JSONB_EXCLUDED = [
        'personal_info' => ['first_name', 'last_name', 'national_id', 'gender', 'birth_date', 'marital_status'],
        'contact_info' => ['email', 'mobile'],
        'employment' => ['personnel_code', 'employment_type', 'hire_date', 'employment_status'],
    ];

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
        // cross-domain to keep a single source of validation rules.
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

        // Real columns — single source of truth for overlapping fields.
        $realData = $this->extractRealFields($data, $sectionKey);
        if (! empty($realData)) {
            $employee->update($realData);
        }

        // JSONB remainder — only the fields not owned by a real column. Sections
        // fully owned by real columns (e.g. employment) skip the JSONB write.
        $jsonbColumn = $section->storage()['jsonb'];
        if ($jsonbColumn) {
            $jsonbData = array_diff_key($data, array_flip(self::JSONB_EXCLUDED[$sectionKey] ?? []));
            $employee->update([$jsonbColumn => $jsonbData]);
        }

        return $employee->fresh();
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
     * Extract the real-column values for a section, mapping section field names
     * to employee columns. Empty strings are normalized to null so nullable
     * unique columns (email, mobile, id_number) never collide.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractRealFields(array $data, string $sectionKey): array
    {
        $realMap = self::REAL_FIELD_MAP[$sectionKey] ?? [];
        $realData = [];

        foreach ($realMap as $field => $column) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            $realData[$column] = is_string($value) && $value === '' ? null : $value;
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
            $jsonbColumn = $section->storage()['jsonb'] ?? null;
            $sectionData = $jsonbColumn ? ($employee->{$jsonbColumn} ?? []) : [];

            foreach (self::REAL_FIELD_MAP[$key] ?? [] as $field => $column) {
                $value = $employee->{$column} ?? null;
                if ($value !== null) {
                    $sectionData[$field] = $value;
                }
            }

            $data[$key] = $sectionData;
        }

        return $data;
    }
}
