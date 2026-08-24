<?php

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Domains\Questionnaire\Sections\EducationSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->employee = Employee::factory()->create();

    $this->academicDegree = DocumentCategory::create([
        'name' => 'مدرک تحصیلی',
        'slug' => 'academic-degree',
        'type' => 'personnel',
    ]);
});

function uploadEducationFile($employee, $category, string $fieldKey)
{
    $user = createUserWithPermissions([
        'employee.documents.upload',
        'employee.documents.view',
    ]);

    return test()->actingAs($user)->postJson(
        "/api/employees/{$employee->id}/documents",
        [
            'document_category_id' => $category->id,
            'file' => UploadedFile::fake()->image('degree.jpg'),
            'section_key' => 'education',
            'field_key' => $fieldKey,
        ],
    );
}

test('an education placement caps degree pages per row', function () {
    uploadEducationFile($this->employee, $this->academicDegree, 'edu-0')->assertCreated();
    uploadEducationFile($this->employee, $this->academicDegree, 'edu-0')->assertCreated();
    uploadEducationFile($this->employee, $this->academicDegree, 'edu-0')->assertCreated();

    // A 4th page for edu-0 is rejected...
    uploadEducationFile($this->employee, $this->academicDegree, 'edu-0')->assertStatus(422);

    // ...but edu-1 has its own independent cap.
    uploadEducationFile($this->employee, $this->academicDegree, 'edu-1')->assertCreated();
});

test('requirements endpoint exposes the education dynamic requirement group', function () {
    $user = createUserWithPermissions(['employee.list']);

    $response = $this->actingAs($user)
        ->getJson('/api/employees/document-requirements')
        ->assertOk();

    $group = collect($response->json('dynamic_requirements'))
        ->firstWhere('section_key', 'education');

    expect($group)->not->toBeNull()
        ->and($group['pattern'])->toBe(EducationSection::FIELD_KEY_PATTERN)
        ->and($group['requirements']['academic-degree']['min_files'])->toBe(1)
        ->and($group['requirements']['academic-degree']['max_files'])->toBe(3);
});

test('education document structure name carries owner, category and row label', function () {
    $this->employee->update([
        'section_education' => ['education_records' => [
            ['degree' => 'کارشناسی', 'field' => 'مهندسی کامپیوتر'],
        ]],
    ]);

    uploadEducationFile($this->employee, $this->academicDegree, 'edu-0')->assertCreated();

    $user = createUserWithPermissions(['employee.documents.view']);

    $response = $this->actingAs($user)
        ->getJson("/api/employees/{$this->employee->id}/documents")
        ->assertOk();

    expect($response->json('data.0.structure_name'))
        ->toBe("{$this->employee->personnel_code} — مدرک تحصیلی — سابقه تحصیلی 1")
        ->and($response->json('data.0.structure_name_slug'))
        ->toBe("{$this->employee->personnel_code}-academic-degree-education-record-1");
});

test('submit blocks until every education row carries its degree pages', function () {
    $this->employee->update([
        'section_education' => ['education_records' => [
            ['degree' => 'کارشناسی'],
            ['degree' => 'کارشناسی ارشد'],
        ]],
    ]);

    $user = createUserWithPermissions(['employee.update']);

    $errors = $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/submit")
        ->json('errors') ?? [];

    expect(array_keys($errors))
        ->toContain('education.education_records.0.academic-degree')
        ->toContain('education.education_records.1.academic-degree');
});

test('submit passes an education row once its degree page is uploaded', function () {
    $this->employee->update([
        'section_education' => ['education_records' => [
            [
                'degree' => 'کارشناسی',
                'field' => 'مهندسی کامپیوتر',
                'institution' => 'دانشگاه تهران',
                'from' => '2016-09-01',
                'to' => '2020-06-30',
                'graduation_date' => '2020-07-15',
                'gpa' => '17.5',
            ],
        ]],
    ]);

    uploadEducationFile($this->employee, $this->academicDegree, 'edu-0')->assertCreated();

    $user = createUserWithPermissions(['employee.update']);

    $errors = $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/submit")
        ->json('errors') ?? [];

    expect(collect(array_keys($errors))
        ->filter(fn (string $key) => str_starts_with($key, 'education.'))
        ->all())->toBe([]);
});
