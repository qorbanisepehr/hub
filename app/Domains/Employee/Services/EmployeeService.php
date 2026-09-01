<?php

namespace App\Domains\Employee\Services;

use App\Domains\Employee\Events\EmployeeCreated;
use App\Domains\Employee\Events\EmployeeDeleted;
use App\Domains\Employee\Events\EmployeeSubmitted;
use App\Domains\Employee\Events\EmployeeUpdated;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Sections\AdditionalInfoSection;
use App\Domains\Employee\Sections\ContactInfoSection;
use App\Domains\Employee\Sections\DependentsSection;
use App\Domains\Employee\Sections\DocumentInquiriesSection;
use App\Domains\Employee\Sections\EmploymentSection;
use App\Domains\Employee\Sections\PersonalInfoSection;
use App\Domains\Employee\Sections\SocialInsuranceSection;
use App\Support\MobileNumber;
use App\Support\Sections\Definitions\EducationSection;
use App\Support\Sections\Definitions\SkillsSection;
use App\Support\Sections\Definitions\TrainingSection;
use App\Support\Sections\Definitions\WorkExperienceSection;
use App\Support\Sections\SectionDefinition;
use App\Support\Sections\SectionRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmployeeService extends SectionRegistry
{
    /**
     * Employee-owned sections (personal/contact/employment/additional info and
     * the HR-only sections) are defined per-domain; the applicant-shape
     * sections (education, work experience, skills, training) are reused from
     * the shared App\Support\Sections\Definitions so Employee stays decoupled
     * from the Questionnaire and Cv domains (ADR-007).
     *
     * @return list<class-string<SectionDefinition>>
     */
    protected function definitions(): array
    {
        return [
            PersonalInfoSection::class,
            ContactInfoSection::class,
            EmploymentSection::class,
            EducationSection::class,
            WorkExperienceSection::class,
            SkillsSection::class,
            TrainingSection::class,
            AdditionalInfoSection::class,
            SocialInsuranceSection::class,
            DependentsSection::class,
            DocumentInquiriesSection::class,
        ];
    }

    /**
     * Employee documents are uploaded in a standalone 'documents' step, so
     * every requirement is placed at the documents section regardless of its
     * declaring section.
     */
    protected function documentsSectionKey(): ?string
    {
        return 'documents';
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

        event(new EmployeeCreated($employee));

        return $employee;
    }

    /**
     * Update the top-level employee attributes, recording an audit event only
     * when the validated fields actually changed.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        $oldValues = $employee->only(array_keys($data));
        $employee->update($data);
        $newValues = $employee->only(array_keys($data));

        $actualChanges = $this->diffAttributes($oldValues, $newValues);

        if ($actualChanges !== []) {
            event(new EmployeeUpdated(
                $employee,
                array_intersect_key($oldValues, $actualChanges),
                array_intersect_key($newValues, $actualChanges),
            ));
        }

        return $employee;
    }

    /**
     * Save a single section (structural validation — draft safe).
     *
     * @param  array<string, mixed>  $data
     */
    public function saveSection(Employee $employee, string $sectionKey, array $data, ?Authenticatable $actor = null): Employee
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

        $data = $section->transformForSave($data, $actor, $employee);
        $oldValues = $employee->toArray();

        $employee = DB::transaction(function () use (
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

        $newValues = $employee->toArray();
        $actualChanges = $this->diffAttributes($oldValues, $newValues);

        if ($actualChanges !== []) {
            event(new EmployeeUpdated(
                $employee,
                array_intersect_key($oldValues, $actualChanges),
                array_intersect_key($newValues, $actualChanges),
                $sectionKey,
            ));
        }

        return $employee;
    }

    /**
     * The permission that authorizes saving the given section: the section's
     * own save permission when declared, else the generic update permission.
     */
    public function savePermissionFor(string $sectionKey): string
    {
        return $this->getSection($sectionKey)->savePermission()
            ?? 'employee.update';
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

        $employee = $employee->fresh();

        event(new EmployeeSubmitted($employee));

        return $employee;
    }

    /**
     * Delete an employee (capture the id before delete so the audit event can
     * reference it) and record the deletion.
     */
    public function delete(Employee $employee): void
    {
        $employeeId = $employee->getKey();
        $employee->delete();

        event(new EmployeeDeleted($employeeId));
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

            $allErrors = array_merge($allErrors, $section->completionDocumentErrors($employee));
        }

        return $allErrors;
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

    /**
     * Compare two attribute arrays and return only keys where values actually differ.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private function diffAttributes(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old) || $old[$key] !== $value) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }
}
