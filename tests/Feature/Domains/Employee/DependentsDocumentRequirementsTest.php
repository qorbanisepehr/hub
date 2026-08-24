<?php

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Sections\DependentsSection;
use App\Domains\FormOptions\Models\FormOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->employee = Employee::factory()->create();

    $this->nationalCard = DocumentCategory::create([
        'name' => 'کارت ملی',
        'slug' => 'national-card',
        'type' => 'personnel',
    ]);

    $this->birthCertificate = DocumentCategory::create([
        'name' => 'شناسنامه',
        'slug' => 'birth-certificate',
        'type' => 'personnel',
    ]);
});

function uploadDependentFile($employee, $category, string $fieldKey)
{
    $user = createUserWithPermissions([
        'employee.documents.upload',
        'employee.documents.view',
    ]);

    return test()->actingAs($user)->postJson(
        "/api/employees/{$employee->id}/documents",
        [
            'document_category_id' => $category->id,
            'file' => UploadedFile::fake()->image('page.jpg'),
            'section_key' => 'dependents',
            'field_key' => $fieldKey,
        ],
    );
}

test('requirements endpoint exposes the dependents dynamic requirement group', function () {
    $user = createUserWithPermissions(['employee.list']);

    $response = $this->actingAs($user)
        ->getJson('/api/employees/document-requirements')
        ->assertOk();

    $group = collect($response->json('dynamic_requirements'))
        ->firstWhere('section_key', 'dependents');

    expect($group)->not->toBeNull()
        ->and($group['pattern'])->toBe(DependentsSection::FIELD_KEY_PATTERN)
        ->and($group['requirements']['national-card']['min_files'])->toBe(2)
        ->and($group['requirements']['birth-certificate']['min_files'])->toBe(5);
});

test('a dependent placement accepts exactly the declared page count', function () {
    uploadDependentFile($this->employee, $this->nationalCard, 'dependent-0')->assertCreated();
    uploadDependentFile($this->employee, $this->nationalCard, 'dependent-0')->assertCreated();
});

test('a dependent placement rejects files beyond the page cap', function () {
    uploadDependentFile($this->employee, $this->nationalCard, 'dependent-0')->assertCreated();
    uploadDependentFile($this->employee, $this->nationalCard, 'dependent-0')->assertCreated();
    uploadDependentFile($this->employee, $this->nationalCard, 'dependent-0')->assertStatus(422);
});

test('page caps are per dependent row', function () {
    uploadDependentFile($this->employee, $this->birthCertificate, 'dependent-0')->assertCreated();

    // dependent-1 has its own independent 5-page group.
    for ($i = 0; $i < 5; $i++) {
        uploadDependentFile($this->employee, $this->birthCertificate, 'dependent-1')->assertCreated();
    }

    // A 6th page for dependent-1 is rejected.
    uploadDependentFile($this->employee, $this->birthCertificate, 'dependent-1')->assertStatus(422);
});

test('employee own national card keeps its one-per-field cap', function () {
    $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.view']);

    $payload = fn (string $fieldKey) => [
        'document_category_id' => $this->nationalCard->id,
        'file' => UploadedFile::fake()->image('card.jpg'),
        'section_key' => 'documents',
        'field_key' => $fieldKey,
    ];

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/documents", $payload('front'))
        ->assertCreated();

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/documents", $payload('front'))
        ->assertStatus(422); // still capped by PersonalInfoSection max_files=1
});

test('dependent document structure name carries owner, category and row label', function () {
    FormOption::create([
        'group' => 'relationship_type',
        'value' => 'child',
        'label' => 'فرزند',
        'sort_order' => 1,
    ]);

    $this->employee->update([
        'section_dependents' => ['dependents' => [
            ['relationship_type' => 'child', 'first_name' => 'علی'],
        ]],
    ]);

    uploadDependentFile($this->employee, $this->nationalCard, 'dependent-0')->assertCreated();

    $user = createUserWithPermissions(['employee.documents.view']);

    $response = $this->actingAs($user)
        ->getJson("/api/employees/{$this->employee->id}/documents")
        ->assertOk();

    expect($response->json('data.0.structure_name'))
        ->toBe("{$this->employee->personnel_code} — کارت ملی — فرزند 1")
        ->and($response->json('data.0.structure_name_slug'))
        ->toBe("{$this->employee->personnel_code}-national-card-child-1");
});

test('submit blocks until every dependent row carries its required pages', function () {
    FormOption::create([
        'group' => 'relationship_type',
        'value' => 'child',
        'label' => 'فرزند',
        'sort_order' => 1,
    ]);

    $this->employee->update([
        'section_dependents' => ['dependents' => [
            ['relationship_type' => 'child', 'first_name' => 'علی'],
        ]],
    ]);

    $user = createUserWithPermissions(['employee.update']);

    $errors = $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/submit")
        ->json('errors') ?? [];

    expect(array_keys($errors))
        ->toContain('dependents.dependents.0.national-card')
        ->toContain('dependents.dependents.0.birth-certificate');
});

test('submit passes a dependent row once its pages are uploaded', function () {
    foreach (
        [
            ['group' => 'relationship_type', 'value' => 'child', 'label' => 'فرزند', 'sort_order' => 1],
            ['group' => 'gender', 'value' => 'male', 'label' => 'مرد', 'sort_order' => 1],
        ] as $option
    ) {
        FormOption::create($option);
    }

    $this->employee->update([
        'section_dependents' => ['dependents' => [[
            'relationship_type' => 'child',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'id_number' => '0123456789',
            'gender' => 'male',
            'birth_date' => '2010-05-01',
        ]]],
    ]);

    for ($i = 0; $i < 2; $i++) {
        uploadDependentFile($this->employee, $this->nationalCard, 'dependent-0')->assertCreated();
    }
    for ($i = 0; $i < 5; $i++) {
        uploadDependentFile($this->employee, $this->birthCertificate, 'dependent-0')->assertCreated();
    }

    $user = createUserWithPermissions(['employee.update']);

    $errors = $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/submit")
        ->json('errors') ?? [];

    expect(collect(array_keys($errors))
        ->filter(fn (string $key) => str_starts_with($key, 'dependents.'))
        ->all())->toBe([]);
});
